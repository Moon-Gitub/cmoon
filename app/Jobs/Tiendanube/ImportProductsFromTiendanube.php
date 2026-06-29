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
        $variant = $tnProduct['variants'][0] ?? [];
        $tnVariantId = $variant['id'] ?? null;

        // Buscar mapeo existente
        $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
            ->where('tn_product_id', $tnProductId)
            ->first();

        $nombre = $tnProduct['name']['es'] ?? $tnProduct['name']['en'] ?? 'Producto TN';
        $descripcion = $tnProduct['description']['es'] ?? $tnProduct['description']['en'] ?? null;
        $sku = $variant['sku'] ?? 'TN-'.$tnProductId;
        $precio = (float) ($variant['price'] ?? 0);
        $costo = (float) ($variant['cost'] ?? 0);

        // Limpiar HTML de descripción
        if ($descripcion) {
            $descripcion = strip_tags($descripcion);
            $descripcion = html_entity_decode($descripcion);
            $descripcion = trim($descripcion);
        }

        if ($map && $map->producto) {
            // Actualizar producto existente
            $map->producto->update([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio_venta' => $precio,
                'precio_compra' => $costo ?: $map->producto->precio_compra,
                'activo' => $tnProduct['published'] ?? true,
            ]);

            $map->update(['last_synced_at' => now()]);

            return 'updated';
        }

        // Verificar si existe producto con mismo código
        $existente = Producto::where('empresa_id', $this->integracion->empresa_id)
            ->where('codigo', $sku)
            ->first();

        if ($existente) {
            // Crear mapeo para producto existente
            TiendanubeProductMap::create([
                'integracion_id' => $this->integracion->id,
                'producto_id' => $existente->id,
                'tn_product_id' => $tnProductId,
                'tn_variant_id' => $tnVariantId,
                'tn_sku' => $sku,
                'last_synced_at' => now(),
            ]);

            return 'updated';
        }

        // Crear nuevo producto
        $categoriaId = $this->findOrCreateCategoria($tnProduct['categories'] ?? []);

        $producto = Producto::create([
            'empresa_id' => $this->integracion->empresa_id,
            'categoria_id' => $categoriaId,
            'codigo' => $sku,
            'nombre' => $nombre,
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

        return 'created';
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
