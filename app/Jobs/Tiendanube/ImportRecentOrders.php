<?php

namespace App\Jobs\Tiendanube;

use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\Venta;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class ImportRecentOrders implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 1800;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public int $days = 7,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_orders) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        $since = now()->subDays($this->days)->toIso8601String();
        $page = 1;
        $imported = 0;
        $skipped = 0;

        TiendanubeLog::registrar(
            $this->integracion,
            'order_import',
            'pull',
            mensaje: "Importando órdenes de los últimos {$this->days} días",
        );

        do {
            $orders = $service->getOrders([
                'created_at_min' => $since,
                'payment_status' => 'paid',
                'per_page' => 50,
                'page' => $page,
            ]);

            foreach ($orders as $order) {
                $orderId = $order['id'];

                // Verificar si ya existe
                if (Venta::where('tn_order_id', $orderId)->exists()) {
                    $skipped++;

                    continue;
                }

                ImportTiendanubeOrder::dispatch($this->integracion, $orderId);
                $imported++;

                usleep(200000); // 0.2 segundos entre dispatches
            }

            $page++;
        } while (count($orders) === 50 && $page <= 20);

        $this->integracion->update(['last_order_sync_at' => now()]);

        TiendanubeLog::registrar(
            $this->integracion,
            'order_import',
            'pull',
            mensaje: "Importación completada: {$imported} nuevas, {$skipped} ya existentes",
        );
    }
}
