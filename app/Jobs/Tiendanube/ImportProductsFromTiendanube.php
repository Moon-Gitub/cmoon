<?php

namespace App\Jobs\Tiendanube;

use App\Models\Categoria;
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

class ImportProductsFromTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public TiendanubeIntegracion $integracion,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo || ! $this->integracion->auto_create_products) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        $page = 1;
        $created = 0;
        $updated = 0;
        $errors = 0;

        TiendanubeLog::registrar(
            $this->integracion,
            'product_sync',
            'pull',
            mensaje: 'Iniciando importación de productos desde Tiendanube',
        );

        do {
            $products = $service->getProducts([
                'per_page' => 50,
                'page' => $page,
            ]);

            foreach ($products as $tnProduct) {
                try {
                    $result = $this->importProduct($tnProduct);
                    if ($result === 'created') {
                        $created++;
                    } elseif ($result === 'updated') {
                        $updated++;
                    }
                } catch (\Throwable $e) {
                    $errors++;
                    report($e);
                }

                usleep(100000);
            }

            $page++;
        } while (count($products) === 50 && $page <= 100);

        TiendanubeLog::registrar(
            $this->integracion,
            'product_sync',
            'pull',
            mensaje: "Importación completada: {$created} creados, {$updated} actualizados, {$errors} errores",
        );
    }

    private function importProduct(array $tnProduct): string
    {
        $tnProductId = $tnProduct['id'];
        $variants = $tnProduct['variants'] ?? [];

        if (empty($variants)) {
            return 'skipped';
        }

        $nombre = $tnProduct['name']['es'] ?? $tnProduct['name']['en'] ?? 'Producto TN';
        $descripcion = $tnProduct['description']['es'] ?? $tnProduct['description']['en'] ?? null;

        // Limpiar HTML de descripción
        if ($descripcion) {
            $descripcion = strip_tags($descripcion);
            $descripcion = html_entity_decode($descripcion);
            $descripcion = trim($descripcion);
        }

        $categoriaId = $this->findOrCreateCategoria($tnProduct['categories'] ?? []);
        $resultType = 'updated';

        // Procesar cada variante como un producto separado (o actualizar si ya existe)
        foreach ($variants as $index => $variant) {
            $tnVariantId = $variant['id'] ?? null;
            $sku = $variant['sku'] ?? "TN-{$tnProductId}-{$index}";
            $precio = (float) ($variant['price'] ?? 0);
            $costo = (float) ($variant['cost'] ?? 0);

            // Construir nombre con atributos de variante si tiene múltiples
            $variantName = $nombre;
            $variantValues = array_filter([
                $variant['values'][0]['es'] ?? $variant['values'][0]['en'] ?? null,
                $variant['values'][1]['es'] ?? $variant['values'][1]['en'] ?? null,
                $variant['values'][2]['es'] ?? $variant['values'][2]['en'] ?? null,
            ]);

            if (count($variants) > 1 && ! empty($variantValues)) {
                $variantName = $nombre.' - '.implode(' / ', $variantValues);
            }

            // Buscar mapeo existente para esta variante
            $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
                ->where('tn_product_id', $tnProductId)
                ->where('tn_variant_id', $tnVariantId)
                ->first();

            if ($map && $map->producto) {
                // Actualizar producto existente
                $map->producto->update([
                    'nombre' => $variantName,
                    'descripcion' => $descripcion,
                    'precio_venta' => $precio,
                    'precio_compra' => $costo ?: $map->producto->precio_compra,
                    'activo' => $tnProduct['published'] ?? true,
                ]);

                $map->update(['last_synced_at' => now(), 'tn_sku' => $sku]);

                continue;
            }

            // Verificar si existe producto con mismo SKU
            $existente = Producto::where('empresa_id', $this->integracion->empresa_id)
                ->where('codigo', $sku)
                ->first();

            if ($existente) {
                // Crear mapeo para producto existente
                TiendanubeProductMap::updateOrCreate(
                    [
                        'integracion_id' => $this->integracion->id,
                        'tn_product_id' => $tnProductId,
                        'tn_variant_id' => $tnVariantId,
                    ],
                    [
                        'producto_id' => $existente->id,
                        'tn_sku' => $sku,
                        'last_synced_at' => now(),
                    ]
                );

                continue;
            }

            // Crear nuevo producto
            $producto = Producto::create([
                'empresa_id' => $this->integracion->empresa_id,
                'categoria_id' => $categoriaId,
                'codigo' => $sku,
                'nombre' => $variantName,
                'descripcion' => $descripcion,
                'precio_venta' => $precio,
                'precio_compra' => $costo,
                'activo' => $tnProduct['published'] ?? true,
            ]);

            TiendanubeProductMap::create([
                'integracion_id' => $this->integracion->id,
                'producto_id' => $producto->id,
                'tn_product_id' => $tnProductId,
                'tn_variant_id' => $tnVariantId,
                'tn_sku' => $sku,
                'last_synced_at' => now(),
            ]);

            $resultType = 'created';
        }

        return $resultType;
    }

    private function findOrCreateCategoria(array $categories): ?int
    {
        if (empty($categories)) {
            return null;
        }

        $tnCategory = $categories[0];
        $tnCategoryId = $tnCategory['id'] ?? null;

        if (! $tnCategoryId) {
            return null;
        }

        // Buscar mapeo existente
        $map = $this->integracion->categoryMaps()
            ->where('tn_category_id', $tnCategoryId)
            ->first();

        if ($map) {
            return $map->categoria_id;
        }

        // Crear categoría
        $nombre = $tnCategory['name']['es'] ?? $tnCategory['name']['en'] ?? 'Categoría TN';

        $categoria = Categoria::firstOrCreate(
            ['empresa_id' => $this->integracion->empresa_id, 'nombre' => $nombre],
            ['activa' => true]
        );

        $this->integracion->categoryMaps()->create([
            'categoria_id' => $categoria->id,
            'tn_category_id' => $tnCategoryId,
        ]);

        return $categoria->id;
    }
}
