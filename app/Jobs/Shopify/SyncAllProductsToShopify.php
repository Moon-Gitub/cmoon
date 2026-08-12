<?php

namespace App\Jobs\Shopify;

use App\Models\Producto;
use App\Models\ShopifyIntegracion;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAllProductsToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public ShopifyIntegracion $integracion,
    ) {}

    public function handle(): void
    {
        if (! $this->integracion->activo || ! $this->integracion->push_products) {
            return;
        }

        Producto::withoutGlobalScopes()
            ->where('empresa_id', $this->integracion->empresa_id)
            ->where('activo', true)
            ->where('publicar_shopify', true)
            ->orderBy('id')
            ->chunkById(50, function ($productos) {
                foreach ($productos as $producto) {
                    SyncProductToShopify::dispatch($this->integracion, $producto);
                }
            });
    }
}
