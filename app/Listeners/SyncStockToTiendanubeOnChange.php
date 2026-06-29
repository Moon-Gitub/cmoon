<?php

namespace App\Listeners;

use App\Jobs\Tiendanube\SyncStockToTiendanube;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeProductMap;

class SyncStockToTiendanubeOnChange
{
    /**
     * Escucha cambios de stock y dispara sincronización a Tiendanube si está configurado.
     *
     * Este listener puede ser llamado desde StockService después de mover/ajustar stock,
     * o mediante un observer en el modelo Stock.
     */
    public function handle(int $productoId, int $empresaId): void
    {
        // Buscar integraciones activas con sync_stock habilitado
        $integraciones = TiendanubeIntegracion::where('empresa_id', $empresaId)
            ->where('activo', true)
            ->where('sync_stock', true)
            ->whereNotNull('default_sucursal_id')
            ->get();

        foreach ($integraciones as $integracion) {
            // Verificar si el producto está mapeado
            $map = TiendanubeProductMap::where('integracion_id', $integracion->id)
                ->where('producto_id', $productoId)
                ->first();

            if ($map && $map->producto) {
                SyncStockToTiendanube::dispatch($integracion, $map->producto)
                    ->delay(now()->addSeconds(5)); // Pequeño delay para agrupar cambios
            }
        }
    }
}
