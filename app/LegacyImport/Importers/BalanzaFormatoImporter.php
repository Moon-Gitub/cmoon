<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\BalanzaFormato;

class BalanzaFormatoImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'balanzas_formatos';
    }

    public function label(): string
    {
        return 'Formatos de balanza';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'balanzas_formatos')) {
            return;
        }

        $query = $ctx->legacy('balanzas_formatos')->orderBy('orden')->orderBy('id');
        if ($this->columnExists($ctx, 'balanzas_formatos', 'id_empresa')) {
            $query->where('id_empresa', $ctx->legacyEmpresaId);
        }

        foreach ($query->get() as $row) {
            if ($this->skipIfMapped($ctx, 'balanza_formato', $row->id)) {
                continue;
            }

            if ($ctx->dryRun) {
                $ctx->remember('balanza_formato', $row->id, (int) $row->id);
                continue;
            }

            $formato = BalanzaFormato::updateOrCreate(
                [
                    'empresa_id' => $ctx->empresaId,
                    'nombre' => $row->nombre,
                    'prefijo' => (string) $row->prefijo,
                ],
                [
                    'longitud_min' => $row->longitud_min !== null ? (int) $row->longitud_min : null,
                    'longitud_max' => $row->longitud_max !== null ? (int) $row->longitud_max : null,
                    'pos_producto' => (int) ($row->pos_producto ?? 0),
                    'longitud_producto' => (int) ($row->longitud_producto ?? 0),
                    'modo_cantidad' => (string) ($row->modo_cantidad ?? 'ninguno'),
                    'pos_cantidad' => $row->pos_cantidad !== null ? (int) $row->pos_cantidad : null,
                    'longitud_cantidad' => $row->longitud_cantidad !== null ? (int) $row->longitud_cantidad : null,
                    'factor_divisor' => (float) ($row->factor_divisor ?? 1),
                    'cantidad_fija' => (float) ($row->cantidad_fija ?? 1),
                    'orden' => (int) ($row->orden ?? 0),
                    'activo' => (bool) ($row->activo ?? true),
                ]
            );

            $ctx->remember('balanza_formato', $row->id, $formato->id);
        }
    }
}
