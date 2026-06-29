<?php

namespace App\Jobs\Tiendanube;

use App\Models\Producto;
use App\Models\Stock;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\TiendanubeProductMap;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncStockToTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public Producto $producto,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_stock) {
            return;
        }

        if (! $this->integracion->default_sucursal_id) {
            return;
        }

        // Buscar mapeo del producto
        $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
            ->where('producto_id', $this->producto->id)
            ->first();

        if (! $map) {
            return;
        }

        // Obtener stock de la sucursal configurada
        $stock = Stock::where('producto_id', $this->producto->id)
            ->where('sucursal_id', $this->integracion->default_sucursal_id)
            ->value('cantidad') ?? 0;

        try {
            $tiendanube->forIntegracion($this->integracion)
                ->updateStock(
                    $map->tn_product_id,
                    (float) $stock,
                    $map->tn_variant_id,
                );

            $this->integracion->update(['last_stock_sync_at' => now()]);

        } catch (\Throwable $e) {
            TiendanubeLog::registrar(
                $this->integracion,
                'stock_sync',
                'push',
                'stock',
                $this->producto->id,
                ['product_id' => $map->tn_product_id, 'stock' => $stock],
                null,
                'error',
                "Error sincronizando stock de {$this->producto->nombre}: {$e->getMessage()}",
            );

            throw $e;
        }
    }
}
