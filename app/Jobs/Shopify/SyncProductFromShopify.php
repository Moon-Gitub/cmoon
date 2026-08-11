<?php

namespace App\Jobs\Shopify;

use App\Models\ShopifyIntegracion;
use App\Models\ShopifyLog;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductFromShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(
        public ShopifyIntegracion $integracion,
        public int $shopifyProductId,
    ) {}

    public function handle(): void
    {
        if (! $this->integracion->activo || ! $this->integracion->auto_create_products) {
            return;
        }

        $product = ShopifyService::make()
            ->forIntegracion($this->integracion)
            ->getProduct($this->shopifyProductId);

        if (! $product) {
            ShopifyLog::registrar(
                $this->integracion,
                'product_sync',
                'pull',
                'product',
                $this->shopifyProductId,
                status: 'error',
                mensaje: "Producto Shopify #{$this->shopifyProductId} no encontrado",
            );

            return;
        }

        $importer = new ImportProductsFromShopify($this->integracion);
        $importer->importSingleProduct($product);
    }
}
