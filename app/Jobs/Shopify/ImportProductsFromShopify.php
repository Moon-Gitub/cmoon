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

class ImportProductsFromShopify implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public ShopifyIntegracion $integracion,
    ) {}

    public function handle(): void
    {
        if (! $this->integracion->activo || ! $this->integracion->auto_create_products) {
            return;
        }

        $service = ShopifyService::make()->forIntegracion($this->integracion);
        $created = 0;
        $updated = 0;
        $errors = 0;
        $pageInfo = null;
        $pages = 0;
        $maxPages = (int) config('shopify.sync.max_pages', 100);

        ShopifyLog::registrar(
            $this->integracion,
            'product_sync',
            'pull',
            mensaje: 'Iniciando importación de productos desde Shopify',
        );

        do {
            $params = ['limit' => config('shopify.sync.chunk_size', 50)];
            if ($pageInfo) {
                $params['page_info'] = $pageInfo;
            }

            $result = $service->getProducts($params);
            $products = $result['products'] ?? [];

            foreach ($products as $shopifyProduct) {
                try {
                    $outcome = $this->importProduct($shopifyProduct);
                    if ($outcome === 'created') {
                        $created++;
                    } elseif ($outcome === 'updated') {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    report($e);
                }

                usleep(80_000);
            }

            $pageInfo = $this->extractNextPageInfo($result['link'] ?? null);
            $pages++;
        } while ($pageInfo && $pages < $maxPages);

        $this->integracion->update(['last_product_sync_at' => now()]);

        ShopifyLog::registrar(
            $this->integracion,
            'product_sync',
            'pull',
            mensaje: "Importación completada: {$created} creados, {$updated} actualizados, {$errors} errores",
        );
    }

    public function importSingleProduct(array $shopifyProduct): string
    {
        return $this->importProduct($shopifyProduct);
    }

    private function importProduct(array $shopifyProduct): string
    {
        $productId = (int) ($shopifyProduct['id'] ?? 0);
        $variants = $shopifyProduct['variants'] ?? [];
        $title = $shopifyProduct['title'] ?? 'Producto Shopify';
        $bodyHtml = $shopifyProduct['body_html'] ?? null;
        $descripcion = $bodyHtml ? trim(html_entity_decode(strip_tags($bodyHtml))) : null;
        $status = ($shopifyProduct['status'] ?? 'active') === 'active';

        if ($productId < 1 || empty($variants)) {
            return 'skipped';
        }

        $resultType = 'updated';

        foreach ($variants as $index => $variant) {
            $variantId = isset($variant['id']) ? (int) $variant['id'] : null;
            $sku = trim((string) ($variant['sku'] ?? ''));
            if ($sku === '') {
                $sku = "SH-{$productId}-{$index}";
            }

            $precio = (float) ($variant['price'] ?? 0);
            $costo = (float) ($variant['cost'] ?? 0);
            $variantTitle = $variant['title'] ?? 'Default Title';
            $nombre = ($variantTitle && $variantTitle !== 'Default Title')
                ? $title.' - '.$variantTitle
                : $title;

            $map = ShopifyProductMap::where('integracion_id', $this->integracion->id)
                ->where('shopify_product_id', $productId)
                ->where('shopify_variant_id', $variantId)
                ->first();

            if ($map?->producto) {
                $map->producto->update([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'precio_venta' => $precio,
                    'precio_compra' => $costo ?: $map->producto->precio_compra,
                    'activo' => $status,
                ]);
                $map->update(['last_synced_at' => now(), 'shopify_sku' => $sku]);

                continue;
            }

            $existente = Producto::withoutGlobalScopes()
                ->where('empresa_id', $this->integracion->empresa_id)
                ->where('codigo', $sku)
                ->first();

            if ($existente) {
                ShopifyProductMap::updateOrCreate(
                    [
                        'integracion_id' => $this->integracion->id,
                        'shopify_product_id' => $productId,
                        'shopify_variant_id' => $variantId,
                    ],
                    [
                        'producto_id' => $existente->id,
                        'shopify_sku' => $sku,
                        'last_synced_at' => now(),
                    ]
                );

                $existente->update([
                    'nombre' => $nombre,
                    'descripcion' => $descripcion,
                    'precio_venta' => $precio,
                    'activo' => $status,
                ]);

                continue;
            }

            $producto = Producto::withoutGlobalScopes()->create([
                'empresa_id' => $this->integracion->empresa_id,
                'codigo' => $sku,
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio_venta' => $precio,
                'precio_compra' => $costo,
                'unidad' => 'UN',
                'activo' => $status,
            ]);

            ShopifyProductMap::create([
                'integracion_id' => $this->integracion->id,
                'producto_id' => $producto->id,
                'shopify_product_id' => $productId,
                'shopify_variant_id' => $variantId,
                'shopify_sku' => $sku,
                'last_synced_at' => now(),
            ]);

            $resultType = 'created';
        }

        return $resultType;
    }

    private function extractNextPageInfo(?string $linkHeader): ?string
    {
        if (! $linkHeader) {
            return null;
        }

        foreach (explode(',', $linkHeader) as $part) {
            if (! str_contains($part, 'rel="next"')) {
                continue;
            }

            if (preg_match('/page_info=([^&>]+)/', $part, $m)) {
                return urldecode($m[1]);
            }
        }

        return null;
    }
}
