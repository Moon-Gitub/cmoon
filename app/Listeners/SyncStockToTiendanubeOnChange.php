<?php

namespace App\Listeners;

use App\Events\StockUpdated;
use App\Jobs\Tiendanube\SyncStockToTiendanube;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeProductMap;

class SyncStockToTiendanubeOnChange
{
    /**
     * Escucha cambios de stock y dispara sincronización a Tiendanube si está configurado.
     */
    public function handle(StockUpdated $event): void
    {
        $producto = $event->producto;
        $sucursalId = $event->sucursalId;

        // Buscar integraciones activas con sync_stock habilitado para esta sucursal
        $integraciones = TiendanubeIntegracion::where('empresa_id', $producto->empresa_id)
            ->where('activo', true)
            ->where('sync_stock', true)
            ->where('default_sucursal_id', $sucursalId)
            ->get();

        foreach ($integraciones as $integracion) {
            // Verificar si el producto está mapeado
            $map = TiendanubeProductMap::where('integracion_id', $integracion->id)
                ->where('producto_id', $producto->id)
                ->first();

            if ($map) {
                SyncStockToTiendanube::dispatch($integracion, $producto)
                    ->delay(now()->addSeconds(3)); // Pequeño delay para agrupar cambios rápidos
            }
        }
    }
}
