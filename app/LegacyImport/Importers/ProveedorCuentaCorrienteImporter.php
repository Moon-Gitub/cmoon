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

            // Esquemas demonew varían: importe / total_compra (tipo 4) / total.
            $importe = (float) ($row->importe ?? 0);
            if ($importe == 0.0) {
                $importe = (float) ($row->total_compra ?? 0);
            }
            if ($importe == 0.0) {
                $importe = (float) ($row->total ?? 0);
            }
            if ($importe == 0.0) {
                continue;
            }

            // Legacy tipo: 1 = pago; resto (2/4/…) = deuda / factura
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

            $fecha = $this->parseDate($row->fecha_movimiento ?? null)
                ?? $this->parseDate($row->fecha ?? null)
                ?? now()->toDateString();

            $mov = MovimientoCuenta::create([
                'titular_type' => $proveedor->getMorphClass(),
                'titular_id' => $proveedor->id,
                'tipo' => $tipo,
                'concepto' => $row->descripcion ?: 'Import legacy CC proveedor',
                'importe' => $importe,
                'user_id' => $ctx->defaultUserId,
                'fecha' => $fecha,
            ]);

            $ctx->remember('cc_proveedor', $row->id, $mov->id);
        }
    }
}
