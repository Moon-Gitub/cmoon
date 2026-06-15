<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Producto;
use App\Models\Stock;

class ProductoImporter extends AbstractImporter
{
    /** @var list<string> */
    private array $stockFields = ['stock', 'stock2', 'stock3', 'deposito', 'ameghino'];

    public function key(): string
    {
        return 'productos';
    }

    public function label(): string
    {
        return 'Productos y stocks por sucursal';
    }

    public function import(LegacyImportContext $ctx): void
    {
        $chunk = config('legacy.chunk_size', 200);

        $ctx->legacy('productos')->orderBy('id')->chunk($chunk, function ($rows) use ($ctx) {
            foreach ($rows as $row) {
                if ($this->skipIfMapped($ctx, 'producto', $row->id)) {
                    continue;
                }

                $codigo = trim((string) $row->codigo);
                if ($codigo === '') {
                    $codigo = 'LEG-'.$row->id;
                }

                $categoriaId = $row->id_categoria
                    ? $ctx->idMap->get('categoria', $row->id_categoria)
                    : null;

                $payload = [
                    'empresa_id' => $ctx->empresaId,
                    'categoria_id' => $categoriaId,
                    'codigo' => $codigo,
                    'nombre' => $row->descripcion ?: $codigo,
                    'descripcion' => $row->descripcion ?: null,
                    'precio_compra' => (float) ($row->precio_compra ?? 0),
                    'precio_venta' => (float) ($row->precio_venta ?? 0),
                    'alicuota_iva' => (float) ($row->tipo_iva ?? 21),
                    'stock_minimo' => (float) ($row->stock_bajo ?? 0),
                    'activo' => (bool) ($row->activo ?? true),
                    'es_combo' => (bool) ($row->es_combo ?? false),
                ];

                if ($ctx->dryRun) {
                    $ctx->remember('producto', $row->id, (int) $row->id);
                    continue;
                }

                $producto = Producto::create($payload);

                foreach ($this->stockFields as $field) {
                    if (! property_exists($row, $field) && ! isset($row->{$field})) {
                        continue;
                    }

                    $cantidad = (float) ($row->{$field} ?? 0);
                    $sucursalId = $ctx->resolveSucursal($field);
                    if (! $sucursalId) {
                        continue;
                    }

                    Stock::updateOrCreate(
                        ['producto_id' => $producto->id, 'sucursal_id' => $sucursalId],
                        ['cantidad' => $cantidad],
                    );
                }

                $ctx->remember('producto', $row->id, $producto->id);
            }
        });
    }
}
