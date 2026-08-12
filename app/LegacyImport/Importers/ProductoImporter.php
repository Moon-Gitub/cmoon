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

                // Algunos clientes legacy (ej. Jamrod) usan `codigo` como nombre
                // largo y `descripcion` como precio textual.
                $descripcionRaw = trim((string) ($row->descripcion ?? ''));
                $nombre = $descripcionRaw !== '' && ! is_numeric($descripcionRaw)
                    ? $descripcionRaw
                    : $codigo;

                $precioVenta = (float) ($row->precio_venta ?? 0);
                if ($precioVenta <= 0 && is_numeric($descripcionRaw)) {
                    $precioVenta = (float) $descripcionRaw;
                }

                $categoriaId = $row->id_categoria
                    ? $ctx->idMap->get('categoria', $row->id_categoria)
                    : null;

                $pesable = $this->esPesable($nombre, $row);

                $payload = [
                    'empresa_id' => $ctx->empresaId,
                    'categoria_id' => $categoriaId,
                    'codigo' => mb_substr($codigo, 0, 255),
                    'nombre' => mb_substr($nombre, 0, 255),
                    'descripcion' => is_numeric($descripcionRaw) ? null : ($descripcionRaw ?: null),
                    'precio_compra' => (float) ($row->precio_compra ?? 0),
                    'precio_venta' => $precioVenta,
                    'alicuota_iva' => (float) ($row->tipo_iva ?? 21),
                    'unidad' => $pesable ? 'KG' : 'UN',
                    'pesable' => $pesable,
                    'stock_minimo' => (float) ($row->stock_bajo ?? 0),
                    'activo' => (bool) ($row->activo ?? true),
                    'es_combo' => (bool) ($row->es_combo ?? false),
                ];

                if ($ctx->dryRun) {
                    $ctx->remember('producto', $row->id, (int) $row->id);
                    continue;
                }

                $producto = Producto::query()
                    ->where('empresa_id', $ctx->empresaId)
                    ->where('codigo', $payload['codigo'])
                    ->first();

                if ($producto) {
                    $producto->update(collect($payload)->except(['empresa_id', 'codigo'])->all());
                } else {
                    try {
                        $producto = Producto::create($payload);
                    } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                        // Carrera / código duplicado en legacy: reutilizar el existente.
                        $producto = Producto::query()
                            ->where('empresa_id', $ctx->empresaId)
                            ->where('codigo', $payload['codigo'])
                            ->firstOrFail();
                    }
                }

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

    private function esPesable(string $nombre, object $row): bool
    {
        if (isset($row->pesable) && (int) $row->pesable === 1) {
            return true;
        }

        $u = strtoupper(trim((string) ($row->unidad ?? $row->unidad_medida ?? '')));
        if (in_array($u, ['KG', 'KILO', 'KILOS', 'GR', 'G', 'GRAMO', 'GRAMOS'], true)) {
            return true;
        }

        $n = mb_strtoupper($nombre);

        return (bool) preg_match('/(?:^|[\s\/xX])KG(?:\b|$)|X\s*KG|POR\s*KG|\/\s*KG/', $n);
    }
}
