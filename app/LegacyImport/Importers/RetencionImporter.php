<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Empresa;
use App\Models\Retencion;

class RetencionImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'retenciones';
    }

    public function label(): string
    {
        return 'Retenciones IIBB históricas';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'proveedores_cuenta_corriente')) {
            return;
        }

        if (! $this->columnExists($ctx, 'proveedores_cuenta_corriente', 'monto_retencion')) {
            return;
        }

        $empresa = $ctx->empresa();

        foreach ($ctx->legacy('proveedores_cuenta_corriente')->orderBy('id')->get() as $row) {
            if ((int) ($row->tipo ?? -1) !== 0 || (float) ($row->monto_retencion ?? 0) <= 0) {
                continue;
            }

            if ($this->skipIfMapped($ctx, 'retencion', $row->id)) {
                continue;
            }

            $proveedorId = $ctx->idMap->get('proveedor', $row->id_proveedor ?? 0);
            if (! $proveedorId) {
                $ctx->stats->inc('retencion', 'errors');
                continue;
            }

            $fecha = $this->parseDate($row->fecha_retencion ?? $row->fecha_movimiento ?? null) ?? now()->toDateString();
            $montoSujeto = (float) ($row->factura_neto ?? $row->importe ?? 0);
            $alicuota = (float) ($row->alicuota_retencion ?? 0);
            $monto = (float) ($row->monto_retencion ?? 0);

            if ($ctx->dryRun) {
                $ctx->remember('retencion', $row->id, (int) $row->id);
                continue;
            }

            $retencion = Retencion::create([
                'empresa_id' => $ctx->empresaId,
                'proveedor_id' => $proveedorId,
                'numero_recibo' => $row->numero_recibo ?? null,
                'user_id' => $ctx->defaultUserId,
                'factura_numero' => $row->factura_numero ?? '0',
                'factura_neto' => $montoSujeto,
                'alicuota' => $alicuota,
                'monto' => $monto,
                'monto_neto_pagado' => max(0, $montoSujeto - $monto),
                'fecha' => $fecha,
                'regimen' => (int) ($empresa->tipo_regimen_retencion_default ?? 101),
                'jurisdiccion' => (int) ($empresa->codigo_jurisdiccion_iibb ?? 913),
                'anulada' => false,
            ]);

            $ctx->remember('retencion', $row->id, $retencion->id);
        }
    }

    private function tableExists(LegacyImportContext $ctx, string $table): bool
    {
        return $ctx->legacy('information_schema.tables')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->exists();
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
