<?php

namespace App\Jobs\Tiendanube;

use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAllStockToTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public TiendanubeIntegracion $integracion,
    ) {}

    public function handle(): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_stock) {
            return;
        }

        if (! $this->integracion->default_sucursal_id) {
            TiendanubeLog::registrar(
                $this->integracion,
                'stock_sync',
                'push',
                status: 'error',
                mensaje: 'No hay sucursal configurada para sincronizar stock',
            );

            return;
        }

        $productMaps = $this->integracion->productMaps()->with('producto')->get();

        $total = $productMaps->count();
        $synced = 0;
        $errors = 0;

        TiendanubeLog::registrar(
            $this->integracion,
            'stock_sync',
            'push',
            mensaje: "Iniciando sincronización de stock para {$total} productos",
        );

        foreach ($productMaps as $map) {
            if (! $map->producto || ! $map->producto->activo) {
                continue;
            }

            try {
                SyncStockToTiendanube::dispatchSync($this->integracion, $map->producto);
                $synced++;

                usleep(300000); // 0.3 segundos entre productos
            } catch (\Throwable $e) {
                $errors++;
                report($e);
            }
        }

        $this->integracion->update(['last_stock_sync_at' => now()]);

        TiendanubeLog::registrar(
            $this->integracion,
            'stock_sync',
            'push',
            mensaje: "Sincronización de stock completada: {$synced}/{$total}, {$errors} errores",
        );
    }
}
