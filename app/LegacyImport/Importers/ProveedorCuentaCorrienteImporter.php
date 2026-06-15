<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\MovimientoCuenta;
use App\Models\Proveedor;

class ProveedorCuentaCorrienteImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'cc_proveedores';
    }

    public function label(): string
    {
        return 'Cuenta corriente de proveedores';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'proveedores_cuenta_corriente')) {
            return;
        }

        foreach ($ctx->legacy('proveedores_cuenta_corriente')->orderBy('id')->get() as $row) {
            if ($this->skipIfMapped($ctx, 'cc_proveedor', $row->id)) {
                continue;
            }

            $proveedorId = $ctx->idMap->get('proveedor', $row->id_proveedor ?? 0);
            if (! $proveedorId) {
                $ctx->stats->inc('cc_proveedor', 'errors');
                continue;
            }

            $proveedor = Proveedor::find($proveedorId);
            if (! $proveedor) {
                continue;
            }

            $importe = (float) ($row->importe ?? 0);
            if ($importe == 0.0) {
                continue;
            }

            // Legacy tipo: interpretamos positivo como deuda con proveedor
            $tipoLegacy = (int) ($row->tipo ?? 0);
            if ($tipoLegacy === 1) {
                $importe = -abs($importe);
                $tipo = 'pago';
            } else {
                $importe = abs($importe);
                $tipo = 'factura';
            }

            if ($ctx->dryRun) {
                $ctx->remember('cc_proveedor', $row->id, (int) $row->id);
                continue;
            }

            MovimientoCuenta::create([
                'titular_type' => $proveedor->getMorphClass(),
                'titular_id' => $proveedor->id,
                'tipo' => $tipo,
                'concepto' => $row->descripcion ?: 'Import legacy CC proveedor',
                'importe' => $importe,
                'user_id' => $ctx->defaultUserId,
                'fecha' => $this->parseDate($row->fecha_movimiento ?? null) ?? now()->toDateString(),
            ]);

            $ctx->remember('cc_proveedor', $row->id, $proveedorId);
        }
    }

    private function tableExists(LegacyImportContext $ctx, string $table): bool
    {
        return $ctx->legacy('information_schema.tables')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->exists();
    }
}
