<?php

namespace App\Jobs\Shopify;

use App\Models\Producto;
use App\Models\ShopifyIntegracion;
use App\Models\ShopifyLog;
use App\Models\ShopifyProductMap;
use App\Services\ShopifyService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncProductToShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public function __construct(
        public ShopifyIntegracion $integracion,
        public Producto $producto,
    ) {}

    public function handle(): void
    {
        if (! $this->integracion->activo || ! $this->integracion->push_products) {
            return;
        }

        if (! $this->producto->publicar_shopify) {
            return;
        }

        $service = ShopifyService::make()->forIntegracion($this->integracion);
        $map = ShopifyProductMap::where('integracion_id', $this->integracion->id)
            ->where('producto_id', $this->producto->id)
            ->first();

        $payload = [
            'title' => $this->producto->nombre,
            'body_html' => $this->producto->descripcion,
            'status' => $this->producto->activo ? 'active' : 'draft',
            'variants' => [[
                'sku' => $this->producto->codigo,
                'price' => (string) $this->producto->precio_venta,
                'inventory_management' => null,
            ]],
        ];

        if ($map?->shopify_product_id) {
            $payload['id'] = $map->shopify_product_id;
            $result = $service->updateProduct((int) $map->shopify_product_id, $payload);
        } else {
            $result = $service->createProduct($payload);
            $variant = $result['variants'][0] ?? null;

            ShopifyProductMap::updateOrCreate(
                [
                    'integracion_id' => $this->integracion->id,
                    'producto_id' => $this->producto->id,
                ],
                [
                    'shopify_product_id' => $result['id'],
                    'shopify_variant_id' => $variant['id'] ?? null,
                    'shopify_sku' => $this->producto->codigo,
                    'last_synced_at' => now(),
                ]
            );
        }

        ShopifyLog::registrar(
            $this->integracion,
            'product_sync',
            'push',
            'product',
            $this->producto->id,
            response: ['shopify_product_id' => $result['id'] ?? null],
            mensaje: "Producto {$this->producto->codigo} sincronizado a Shopify",
        );
    }
}
