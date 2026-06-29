<?php

namespace App\Jobs\Tiendanube;

use App\Models\Producto;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\TiendanubeProductMap;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncPromotionalPrices implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_prices) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);
        $synced = 0;
        $errors = 0;

        // Buscar productos con precio promocional activo
        $productos = Producto::where('empresa_id', $this->integracion->empresa_id)
            ->where('activo', true)
            ->whereNotNull('precio_promocional')
            ->where('precio_promocional', '>', 0)
            ->where(function ($q) {
                $q->whereNull('promo_desde')
                    ->orWhere('promo_desde', '<=', now());
            })
            ->where(function ($q) {
                $q->whereNull('promo_hasta')
                    ->orWhere('promo_hasta', '>=', now());
            })
            ->get();

        foreach ($productos as $producto) {
            $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
                ->where('producto_id', $producto->id)
                ->first();

            if (! $map) {
                continue;
            }

            try {
                $service->setPromotionalPrice(
                    $map->tn_product_id,
                    $map->tn_variant_id,
                    (float) $producto->precio_promocional,
                    $producto->promo_desde?->toDateString(),
                    $producto->promo_hasta?->toDateString(),
                );

                $synced++;
            } catch (\Throwable $e) {
                $errors++;
                TiendanubeLog::registrar(
                    $this->integracion,
                    'promo_price',
                    'push',
                    'product',
                    $producto->id,
                    status: 'error',
                    mensaje: "Error sync promo {$producto->nombre}: {$e->getMessage()}",
                );
            }
        }

        // Quitar precios promocionales vencidos
        $productosVencidos = Producto::where('empresa_id', $this->integracion->empresa_id)
            ->where('activo', true)
            ->whereNotNull('promo_hasta')
            ->where('promo_hasta', '<', now())
            ->get();

        foreach ($productosVencidos as $producto) {
            $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
                ->where('producto_id', $producto->id)
                ->first();

            if (! $map) {
                continue;
            }

            try {
                $service->removePromotionalPrice($map->tn_product_id, $map->tn_variant_id);

                // Limpiar en local también
                $producto->update([
                    'precio_promocional' => null,
                    'promo_desde' => null,
                    'promo_hasta' => null,
                ]);
            } catch (\Throwable) {
                // Ignorar errores al quitar promos
            }
        }

        TiendanubeLog::registrar(
            $this->integracion,
            'promo_price',
            'push',
            mensaje: "Sincronizados {$synced} precios promocionales, {$errors} errores",
        );
    }
}
