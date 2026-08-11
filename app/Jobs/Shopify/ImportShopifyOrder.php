<?php

namespace App\Jobs\Shopify;

use App\Models\Cliente;
use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\ShopifyIntegracion;
use App\Models\ShopifyLog;
use App\Models\ShopifyProductMap;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\ShopifyService;
use App\Services\StockService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportShopifyOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public ShopifyIntegracion $integracion,
        public int $orderId,
        public ?array $payload = null,
    ) {}

    public function handle(StockService $stockService): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_orders) {
            return;
        }

        if (Venta::withoutGlobalScopes()->where('shopify_order_id', $this->orderId)->exists()) {
            return;
        }

        $order = $this->payload;
        if (! $order || ! isset($order['id'])) {
            $order = ShopifyService::make()->forIntegracion($this->integracion)->getOrder($this->orderId);
        }

        if (! $order) {
            ShopifyLog::registrar(
                $this->integracion,
                'order_import',
                'pull',
                'order',
                $this->orderId,
                status: 'error',
                mensaje: "Orden #{$this->orderId} no encontrada en Shopify",
            );

            return;
        }

        if (! $this->integracion->default_sucursal_id) {
            ShopifyLog::registrar(
                $this->integracion,
                'order_import',
                'pull',
                'order',
                $this->orderId,
                status: 'error',
                mensaje: 'Configurá una sucursal default antes de importar órdenes',
            );

            return;
        }

        DB::transaction(function () use ($order, $stockService) {
            $cliente = $this->findOrCreateCliente($order['customer'] ?? null);
            $userId = \App\Models\User::where('empresa_id', $this->integracion->empresa_id)
                ->where('activo', true)
                ->orderBy('id')
                ->value('id');

            if (! $userId) {
                throw new \RuntimeException('No hay usuario activo en la empresa para asociar la venta Shopify');
            }

            $subtotal = (float) ($order['subtotal_price'] ?? $order['total_line_items_price'] ?? 0);
            $total = (float) ($order['total_price'] ?? 0);
            $descuento = (float) ($order['total_discounts'] ?? 0);

            $venta = Venta::withoutGlobalScopes()->create([
                'uuid' => (string) Str::uuid(),
                'empresa_id' => $this->integracion->empresa_id,
                'sucursal_id' => $this->integracion->default_sucursal_id,
                'cliente_id' => $cliente?->id,
                'user_id' => $userId,
                'numero' => $this->generateNumero(),
                'estado' => 'completada',
                'origen' => 'shopify',
                'shopify_order_id' => $order['id'],
                'shopify_order_number' => (string) ($order['name'] ?? $order['order_number'] ?? $order['id']),
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'recargo' => 0,
                'total' => $total,
                'fecha' => $order['created_at'] ?? now(),
            ]);

            foreach ($order['line_items'] ?? [] as $item) {
                $this->createVentaItem($venta, $item, $stockService);
            }

            $this->createPago($venta, $order);

            ShopifyLog::registrar(
                $this->integracion,
                'order_import',
                'pull',
                'order',
                $this->orderId,
                response: ['venta_id' => $venta->id, 'numero' => $venta->numero],
                mensaje: 'Orden Shopify importada como venta #'.$venta->numero,
            );
        });

        $this->integracion->update(['last_order_sync_at' => now()]);
    }

    private function findOrCreateCliente(?array $customer): ?Cliente
    {
        if (! $customer) {
            return null;
        }

        $email = $customer['email'] ?? null;
        $nombre = trim(($customer['first_name'] ?? '').' '.($customer['last_name'] ?? '')) ?: ($email ?: 'Cliente Shopify');

        if ($email) {
            $existente = Cliente::withoutGlobalScopes()
                ->where('empresa_id', $this->integracion->empresa_id)
                ->where('email', $email)
                ->first();

            if ($existente) {
                return $existente;
            }
        }

        return Cliente::withoutGlobalScopes()->create([
            'empresa_id' => $this->integracion->empresa_id,
            'nombre' => $nombre,
            'email' => $email,
            'telefono' => $customer['phone'] ?? null,
            'tipo_documento' => 'DNI',
            'documento' => null,
            'activo' => true,
        ]);
    }

    private function createVentaItem(Venta $venta, array $item, StockService $stockService): void
    {
        $sku = $item['sku'] ?? null;
        $variantId = isset($item['variant_id']) ? (int) $item['variant_id'] : null;
        $productId = isset($item['product_id']) ? (int) $item['product_id'] : null;
        $cantidad = (float) ($item['quantity'] ?? 1);
        $precio = (float) ($item['price'] ?? 0);

        $producto = null;

        if ($variantId || $productId) {
            $mapQuery = ShopifyProductMap::where('integracion_id', $this->integracion->id);
            if ($variantId) {
                $mapQuery->where('shopify_variant_id', $variantId);
            } elseif ($productId) {
                $mapQuery->where('shopify_product_id', $productId);
            }
            $map = $mapQuery->first();
            $producto = $map?->producto;
        }

        if (! $producto && $sku) {
            $producto = Producto::withoutGlobalScopes()
                ->where('empresa_id', $this->integracion->empresa_id)
                ->where('codigo', $sku)
                ->first();
        }

        VentaItem::create([
            'venta_id' => $venta->id,
            'producto_id' => $producto?->id,
            'descripcion' => $item['title'] ?? ($item['name'] ?? 'Item Shopify'),
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'alicuota_iva' => $producto?->alicuota_iva ?? 21,
            'total' => $cantidad * $precio,
        ]);

        if ($producto && $venta->sucursal_id) {
            try {
                $stockService->mover(
                    $producto,
                    (int) $venta->sucursal_id,
                    -$cantidad,
                    'venta',
                    'Orden Shopify '.$venta->shopify_order_number,
                    $venta,
                );
            } catch (\Throwable $e) {
                report($e);
            }
        }
    }

    private function createPago(Venta $venta, array $order): void
    {
        $medio = MedioPago::withoutGlobalScopes()
            ->where('empresa_id', $this->integracion->empresa_id)
            ->where('activo', true)
            ->orderBy('id')
            ->first();

        if (! $medio) {
            return;
        }

        VentaPago::create([
            'venta_id' => $venta->id,
            'medio_pago_id' => $medio->id,
            'importe' => (float) ($order['total_price'] ?? $venta->total),
        ]);
    }

    private function generateNumero(): int
    {
        return (int) Venta::withoutGlobalScopes()
            ->where('empresa_id', $this->integracion->empresa_id)
            ->lockForUpdate()
            ->max('numero') + 1;
    }
}
