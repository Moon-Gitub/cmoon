<?php

namespace App\Jobs\Tiendanube;

use App\Models\Cliente;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;

class ImportAbandonedCarts implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 120;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public int $daysBack = 7,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        $imported = 0;
        $page = 1;
        $since = now()->subDays($this->daysBack)->toIso8601String();

        do {
            $checkouts = $service->getAbandonedCheckouts([
                'page' => $page,
                'per_page' => 50,
                'created_at_min' => $since,
            ]);

            foreach ($checkouts as $checkout) {
                if ($this->importCheckout($checkout)) {
                    $imported++;
                }
            }

            $page++;
        } while (count($checkouts) === 50 && $page <= 10);

        TiendanubeLog::registrar(
            $this->integracion,
            'abandoned_cart',
            'pull',
            mensaje: "Importados {$imported} carritos abandonados",
        );

        $this->integracion->update(['last_abandoned_sync_at' => now()]);
    }

    private function importCheckout(array $checkout): bool
    {
        $checkoutId = $checkout['id'];
        $email = $checkout['contact_email'] ?? $checkout['customer']['email'] ?? null;

        if (! $email) {
            return false;
        }

        // Verificar si ya existe un lead/cliente con este checkout
        $existingLead = DB::table('leads_abandonados')
            ->where('tn_checkout_id', $checkoutId)
            ->exists();

        if ($existingLead) {
            return false;
        }

        // Buscar o crear cliente
        $cliente = Cliente::where('empresa_id', $this->integracion->empresa_id)
            ->where('email', $email)
            ->first();

        if (! $cliente) {
            $nombre = trim(($checkout['contact_name'] ?? '').' '.($checkout['contact_lastname'] ?? ''));

            if (empty($nombre)) {
                $nombre = $checkout['customer']['name'] ?? 'Lead Tiendanube';
            }

            $cliente = Cliente::create([
                'empresa_id' => $this->integracion->empresa_id,
                'nombre' => $nombre,
                'email' => $email,
                'telefono' => $checkout['contact_phone'] ?? $checkout['customer']['phone'] ?? null,
                'origen' => 'tiendanube_abandoned',
                'activo' => true,
            ]);
        }

        // Calcular valor del carrito
        $total = 0;
        $items = [];

        foreach ($checkout['products'] ?? [] as $product) {
            $subtotal = ($product['price'] ?? 0) * ($product['quantity'] ?? 1);
            $total += $subtotal;
            $items[] = [
                'nombre' => $product['name'] ?? 'Producto',
                'cantidad' => $product['quantity'] ?? 1,
                'precio' => $product['price'] ?? 0,
            ];
        }

        // Guardar lead abandonado
        DB::table('leads_abandonados')->insert([
            'empresa_id' => $this->integracion->empresa_id,
            'cliente_id' => $cliente->id,
            'tn_checkout_id' => $checkoutId,
            'email' => $email,
            'telefono' => $checkout['contact_phone'] ?? null,
            'total_carrito' => $total,
            'productos_json' => json_encode($items),
            'checkout_url' => $checkout['recovery_url'] ?? null,
            'abandonado_at' => $checkout['created_at'] ?? now(),
            'contactado' => false,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return true;
    }
}
