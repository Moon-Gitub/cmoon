<?php

namespace App\Jobs\Tiendanube;

use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\TiendanubeProductMap;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncLocationsStock implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_stock) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        // Obtener ubicaciones de Tiendanube
        $tnLocations = $service->getLocations();

        if (empty($tnLocations)) {
            return;
        }

        // Obtener sucursales locales con mapeo a Tiendanube
        $sucursales = Sucursal::where('empresa_id', $this->integracion->empresa_id)
            ->where('activa', true)
            ->whereNotNull('tn_location_id')
            ->get();

        if ($sucursales->isEmpty()) {
            TiendanubeLog::registrar(
                $this->integracion,
                'location_sync',
                'push',
                status: 'error',
                mensaje: 'No hay sucursales con ubicación de Tiendanube configurada',
            );

            return;
        }

        $synced = 0;
        $errors = 0;

        // Obtener todos los mapeos de productos
        $maps = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
            ->whereNotNull('tn_variant_id')
            ->get();

        foreach ($maps as $map) {
            foreach ($sucursales as $sucursal) {
                $stock = Stock::where('producto_id', $map->producto_id)
                    ->where('sucursal_id', $sucursal->id)
                    ->first();

                $cantidad = (int) ($stock->cantidad ?? 0);

                try {
                    $service->updateStockAtLocation(
                        $map->tn_product_id,
                        $sucursal->tn_location_id,
                        $cantidad,
                        $map->tn_variant_id,
                    );

                    $synced++;
                } catch (\Throwable $e) {
                    $errors++;
                }
            }
        }

        TiendanubeLog::registrar(
            $this->integracion,
            'location_sync',
            'push',
            mensaje: "Sincronizado stock en {$sucursales->count()} ubicaciones: {$synced} OK, {$errors} errores",
        );
    }
}
