<?php

namespace App\Jobs\Tiendanube;

use App\Models\Cliente;
use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\TiendanubeProductMap;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use App\Services\StockService;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ImportTiendanubeOrder implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public int $orderId,
    ) {}

    public function handle(TiendanubeService $tiendanube, StockService $stockService): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_orders) {
            return;
        }

        // Verificar si ya existe
        if (Venta::where('tn_order_id', $this->orderId)->exists()) {
            return;
        }

        $order = $tiendanube->forIntegracion($this->integracion)->getOrder($this->orderId);

        if (! $order) {
            TiendanubeLog::registrar(
                $this->integracion,
                'order_import',
                'pull',
                'order',
                $this->orderId,
                status: 'error',
                mensaje: "Orden #{$this->orderId} no encontrada en Tiendanube",
            );

            return;
        }

        DB::transaction(function () use ($order, $stockService) {
            // Buscar o crear cliente
            $cliente = $this->findOrCreateCliente($order['customer'] ?? null);

            // Crear venta
            $venta = Venta::create([
                'uuid' => Str::uuid(),
                'empresa_id' => $this->integracion->empresa_id,
                'sucursal_id' => $this->integracion->default_sucursal_id,
                'cliente_id' => $cliente?->id,
                'user_id' => null,
                'numero' => $this->generateNumero(),
                'estado' => 'completada',
                'origen' => 'tiendanube',
                'tn_order_id' => $order['id'],
                'tn_order_number' => $order['number'] ?? null,
                'subtotal' => (float) ($order['subtotal'] ?? 0),
                'descuento' => (float) ($order['discount'] ?? 0),
                'recargo' => (float) ($order['shipping_cost_customer'] ?? 0),
                'total' => (float) ($order['total'] ?? 0),
                'fecha' => $order['created_at'] ?? now(),
            ]);

            // Crear items
            foreach ($order['products'] ?? [] as $product) {
                $this->createVentaItem($venta, $product, $stockService);
            }

            // Crear pago
            $this->createPago($venta, $order);

            TiendanubeLog::registrar(
                $this->integracion,
                'order_import',
                'pull',
                'order',
                $this->orderId,
                response: ['venta_id' => $venta->id, 'numero' => $venta->numero],
                mensaje: "Orden #{$order['number']} importada como venta #{$venta->numero}",
            );
        });

        $this->integracion->update(['last_order_sync_at' => now()]);
    }

    private function findOrCreateCliente(?array $customerData): ?Cliente
    {
        if (! $customerData) {
            return null;
        }

        $email = $customerData['email'] ?? null;
        $identification = $customerData['identification'] ?? null;

        if ($email) {
            $cliente = Cliente::where('empresa_id', $this->integracion->empresa_id)
                ->where('email', $email)
                ->first();

            if ($cliente) {
                return $cliente;
            }
        }

        if ($identification) {
            $cliente = Cliente::where('empresa_id', $this->integracion->empresa_id)
                ->where('documento', $identification)
                ->first();

            if ($cliente) {
                return $cliente;
            }
        }

        // Crear cliente si tiene datos
        $nombre = trim(($customerData['name'] ?? '').' '.($customerData['last_name'] ?? ''));

        if (! $nombre && ! $email) {
            return null;
        }

        return Cliente::create([
            'empresa_id' => $this->integracion->empresa_id,
            'nombre' => $nombre ?: 'Cliente Tiendanube',
            'email' => $email,
            'telefono' => $customerData['phone'] ?? null,
            'documento' => $identification,
            'tipo_documento' => $identification ? 'CUIT' : 'OTRO',
            'condicion_iva' => 'CF',
            'direccion' => $this->buildAddress($customerData['default_address'] ?? null),
        ]);
    }

    private function buildAddress(?array $address): ?string
    {
        if (! $address) {
            return null;
        }

        $parts = array_filter([
            $address['address'] ?? null,
            $address['number'] ?? null,
            $address['floor'] ?? null,
            $address['locality'] ?? null,
            $address['city'] ?? null,
            $address['province'] ?? null,
        ]);

        return implode(', ', $parts) ?: null;
    }

    private function createVentaItem(Venta $venta, array $product, StockService $stockService): void
    {
        $cantidad = (float) ($product['quantity'] ?? 1);
        $precio = (float) ($product['price'] ?? 0);

        // Buscar producto local por mapeo o SKU
        $productoLocal = null;
        $tnProductId = $product['product_id'] ?? null;
        $tnVariantId = $product['variant_id'] ?? null;

        if ($tnProductId) {
            $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
                ->where('tn_product_id', $tnProductId)
                ->when($tnVariantId, fn ($q) => $q->where('tn_variant_id', $tnVariantId))
                ->first();

            if ($map) {
                $productoLocal = $map->producto;
            }
        }

        // Buscar por SKU si no hay mapeo
        if (! $productoLocal && ! empty($product['sku'])) {
            $productoLocal = Producto::where('empresa_id', $this->integracion->empresa_id)
                ->where('codigo', $product['sku'])
                ->first();
        }

        VentaItem::create([
            'venta_id' => $venta->id,
            'producto_id' => $productoLocal?->id,
            'codigo' => $product['sku'] ?? $productoLocal?->codigo ?? 'TN-'.$tnProductId,
            'nombre' => $product['name'] ?? $productoLocal?->nombre ?? 'Producto Tiendanube',
            'cantidad' => $cantidad,
            'precio_unitario' => $precio,
            'subtotal' => $cantidad * $precio,
        ]);

        // Descontar stock si hay producto local
        if ($productoLocal && $this->integracion->default_sucursal_id) {
            $stockService->mover(
                $productoLocal,
                $this->integracion->default_sucursal_id,
                -$cantidad,
                'venta',
                "Venta Tiendanube #{$venta->tn_order_number}",
                $venta,
            );
        }
    }

    private function createPago(Venta $venta, array $order): void
    {
        $medioPago = MedioPago::where('empresa_id', $this->integracion->empresa_id)
            ->where('nombre', 'like', '%tiendanube%')
            ->first();

        if (! $medioPago) {
            $medioPago = MedioPago::where('empresa_id', $this->integracion->empresa_id)
                ->where('activo', true)
                ->first();
        }

        VentaPago::create([
            'venta_id' => $venta->id,
            'medio_pago_id' => $medioPago?->id,
            'monto' => $venta->total,
            'referencia' => 'TN-'.($order['payment_details']['method'] ?? 'online'),
        ]);
    }

    private function generateNumero(): string
    {
        $ultimo = Venta::where('empresa_id', $this->integracion->empresa_id)
            ->where('origen', 'tiendanube')
            ->max('numero');

        $num = $ultimo ? ((int) preg_replace('/\D/', '', $ultimo)) + 1 : 1;

        return 'TN-'.str_pad($num, 6, '0', STR_PAD_LEFT);
    }
}
