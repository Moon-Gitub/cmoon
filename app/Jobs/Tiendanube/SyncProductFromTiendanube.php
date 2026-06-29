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

class SyncProductFromTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public int $tnProductId,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo) {
            return;
        }

        $tnProduct = $tiendanube->forIntegracion($this->integracion)->getProduct($this->tnProductId);

        if (! $tnProduct) {
            return;
        }

        $variant = $tnProduct['variants'][0] ?? [];
        $tnVariantId = $variant['id'] ?? null;

        // Buscar mapeo existente
        $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
            ->where('tn_product_id', $this->tnProductId)
            ->first();

        $nombre = $tnProduct['name']['es'] ?? $tnProduct['name']['en'] ?? 'Producto TN';
        $descripcion = $tnProduct['description']['es'] ?? $tnProduct['description']['en'] ?? null;
        $sku = $variant['sku'] ?? 'TN-'.$this->tnProductId;
        $precio = (float) ($variant['price'] ?? 0);

        if ($descripcion) {
            $descripcion = strip_tags($descripcion);
            $descripcion = html_entity_decode($descripcion);
            $descripcion = trim($descripcion);
        }

        if ($map && $map->producto) {
            $map->producto->update([
                'nombre' => $nombre,
                'descripcion' => $descripcion,
                'precio_venta' => $precio,
                'activo' => $tnProduct['published'] ?? true,
            ]);

            $map->update(['last_synced_at' => now()]);

            TiendanubeLog::registrar(
                $this->integracion,
                'product_sync',
                'pull',
                'product',
                $map->producto_id,
                mensaje: "Producto actualizado desde Tiendanube: {$nombre}",
            );

            return;
        }

        if (! $this->integracion->auto_create_products) {
            return;
        }

        // Crear producto nuevo
        $categoriaId = null;
        if (! empty($tnProduct['categories'])) {
            $tnCat = $tnProduct['categories'][0];
            $catMap = $this->integracion->categoryMaps()
                ->where('tn_category_id', $tnCat['id'])
                ->first();

            if ($catMap) {
                $categoriaId = $catMap->categoria_id;
            } else {
                $catNombre = $tnCat['name']['es'] ?? $tnCat['name']['en'] ?? 'Categoría';
                $categoria = Categoria::firstOrCreate(
                    ['empresa_id' => $this->integracion->empresa_id, 'nombre' => $catNombre],
                    ['activa' => true]
                );
                $categoriaId = $categoria->id;

                $this->integracion->categoryMaps()->create([
                    'categoria_id' => $categoria->id,
                    'tn_category_id' => $tnCat['id'],
                ]);
            }
        }

        $producto = Producto::create([
            'empresa_id' => $this->integracion->empresa_id,
            'categoria_id' => $categoriaId,
            'codigo' => $sku,
            'nombre' => $nombre,
            'descripcion' => $descripcion,
            'precio_venta' => $precio,
            'activo' => $tnProduct['published'] ?? true,
        ]);

        TiendanubeProductMap::create([
            'integracion_id' => $this->integracion->id,
            'producto_id' => $producto->id,
            'tn_product_id' => $this->tnProductId,
            'tn_variant_id' => $tnVariantId,
            'tn_sku' => $sku,
            'last_synced_at' => now(),
        ]);

        TiendanubeLog::registrar(
            $this->integracion,
            'product_sync',
            'pull',
            'product',
            $producto->id,
            mensaje: "Producto creado desde Tiendanube: {$nombre}",
        );
    }
}
