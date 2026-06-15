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
                $porcentaje = (float) ($row->valor_descuento ?? 0);
            }

            if ($ctx->dryRun) {
                $ctx->remember('lista_precio', $row->id, (int) $row->id);
                continue;
            }

            $lista = ListaPrecio::firstOrCreate(
                ['empresa_id' => $ctx->empresaId, 'nombre' => $row->nombre],
                ['porcentaje' => $porcentaje, 'activa' => (bool) ($row->activo ?? true)],
            );

            $ctx->remember('lista_precio', $row->id, $lista->id);
        }
    }

    private function columnExists(LegacyImportContext $ctx, string $table, string $column): bool
    {
        return $ctx->legacy('information_schema.columns')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
    }
}
