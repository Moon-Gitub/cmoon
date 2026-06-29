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

class SyncProductToTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public Producto $producto,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_products) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        // Buscar mapeo existente
        $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
            ->where('producto_id', $this->producto->id)
            ->first();

        $productData = $this->buildProductData();

        try {
            if ($map) {
                // Actualizar producto existente
                $result = $service->updateProduct($map->tn_product_id, $productData);

                $map->update([
                    'last_synced_at' => now(),
                    'tn_sku' => $this->producto->codigo,
                ]);

                // Sincronizar imagen si existe y no tiene imágenes en TN
                $this->syncImage($service, $map->tn_product_id);
            } else {
                // Crear nuevo producto
                $result = $service->createProduct($productData);

                $tnProductId = $result['id'];

                TiendanubeProductMap::create([
                    'integracion_id' => $this->integracion->id,
                    'producto_id' => $this->producto->id,
                    'tn_product_id' => $tnProductId,
                    'tn_variant_id' => $result['variants'][0]['id'] ?? null,
                    'tn_sku' => $this->producto->codigo,
                    'last_synced_at' => now(),
                ]);

                // Subir imagen si existe
                $this->syncImage($service, $tnProductId);
            }

            // Sincronizar stock si está habilitado
            if ($this->integracion->sync_stock && $this->integracion->default_sucursal_id) {
                SyncStockToTiendanube::dispatch($this->integracion, $this->producto);
            }

        } catch (\Throwable $e) {
            TiendanubeLog::registrar(
                $this->integracion,
                'product_sync',
                'push',
                'product',
                $this->producto->id,
                $productData,
                null,
                'error',
                "Error sincronizando {$this->producto->nombre}: {$e->getMessage()}",
            );

            throw $e;
        }
    }

    private function buildProductData(): array
    {
        $lang = config('tiendanube.product_mapping.default_language', 'es');

        $data = [
            'name' => [$lang => $this->producto->nombre],
            'published' => $this->producto->activo,
            'variants' => [
                [
                    'sku' => $this->producto->codigo,
                    'price' => (float) $this->producto->precio_venta,
                    'stock_management' => true,
                ],
            ],
        ];

        if ($this->producto->descripcion) {
            $data['description'] = [$lang => $this->producto->descripcion];
        }

        if ($this->producto->precio_compra > 0) {
            $data['variants'][0]['cost'] = (float) $this->producto->precio_compra;
        }

        // Categoría
        if ($this->producto->categoria_id) {
            $categoryMap = $this->integracion->categoryMaps()
                ->where('categoria_id', $this->producto->categoria_id)
                ->first();

            if ($categoryMap) {
                $data['categories'] = [$categoryMap->tn_category_id];
            }
        }

        return $data;
    }

    private function syncImage(TiendanubeService $service, int $tnProductId): void
    {
        if (! $this->producto->imagen_path) {
            return;
        }

        // Verificar si ya tiene imágenes
        $existingImages = $service->getProductImages($tnProductId);

        if (! empty($existingImages)) {
            return; // Ya tiene imágenes, no reemplazar
        }

        // Construir URL pública de la imagen
        $imagePath = $this->producto->imagen_path;

        // Si es una URL completa, usarla directamente
        if (str_starts_with($imagePath, 'http')) {
            $service->uploadProductImage($tnProductId, $imagePath);

            return;
        }

        // Si es un path relativo, construir URL pública
        $baseUrl = config('app.url');
        $imageUrl = "{$baseUrl}/storage/{$imagePath}";

        $service->uploadProductImage($tnProductId, $imageUrl);
    }
}
