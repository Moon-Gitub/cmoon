<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\ListaPrecio;

class ListaPrecioImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'listas_precio';
    }

    public function label(): string
    {
        return 'Listas de precio';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'listas_precio')) {
            return;
        }

        $query = $ctx->legacy('listas_precio')->orderBy('id');

        if ($this->columnExists($ctx, 'listas_precio', 'id_empresa')) {
            $query->where('id_empresa', $ctx->legacyEmpresaId);
        }

        foreach ($query->get() as $row) {
            if ($this->skipIfMapped($ctx, 'lista_precio', $row->id)) {
                continue;
            }

            $porcentaje = 0.0;
            if (($row->tipo_descuento ?? '') === 'porcentaje') {
                // En demonew valor_descuento es descuento (resta). En cmoon porcentaje
                // positivo = recargo; negativo = descuento → invertir el signo.
                $porcentaje = -abs((float) ($row->valor_descuento ?? 0));
            }

            $basePrecio = (string) ($row->base_precio ?? 'precio_venta');
            $base = in_array($basePrecio, ['precio_compra', 'precioCosto', 'costo'], true)
                ? 'compra'
                : 'venta';

            if ($ctx->dryRun) {
                $ctx->remember('lista_precio', $row->id, (int) $row->id);
                continue;
            }

            $lista = ListaPrecio::firstOrCreate(
                ['empresa_id' => $ctx->empresaId, 'nombre' => $row->nombre],
                [
                    'porcentaje' => $porcentaje,
                    'base' => $base,
                    'activa' => (bool) ($row->activo ?? true),
                ],
            );

            if ($lista->base !== $base || (float) $lista->porcentaje !== $porcentaje) {
                $lista->update(['base' => $base, 'porcentaje' => $porcentaje]);
            }

            $ctx->remember('lista_precio', $row->id, $lista->id);
        }
    }
}
