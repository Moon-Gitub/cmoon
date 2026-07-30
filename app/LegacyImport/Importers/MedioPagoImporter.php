<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Mappers\MedioPagoTipoMapper;
use App\LegacyImport\Support\LegacyImportContext;
use App\Models\MedioPago;

class MedioPagoImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'medios_pago';
    }

    public function label(): string
    {
        return 'Medios de pago';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'medios_pago')) {
            $this->ensureDefaultMedios($ctx);

            return;
        }

        foreach ($ctx->legacy('medios_pago')->orderBy('id')->get() as $row) {
            if ($this->skipIfMapped($ctx, 'medio_pago', $row->id)) {
                continue;
            }

            $nombre = trim((string) $row->nombre);
            if ($nombre === '') {
                $ctx->stats->inc('medio_pago', 'errors');
                continue;
            }

            $tipo = MedioPagoTipoMapper::fromLegacyCodigoOrNombre($row->codigo ?? null, $nombre);

            if ($ctx->dryRun) {
                $ctx->remember('medio_pago', $row->id, (int) $row->id);
                continue;
            }

            $medio = MedioPago::firstOrCreate(
                ['empresa_id' => $ctx->empresaId, 'nombre' => $nombre],
                ['tipo' => $tipo, 'activo' => (bool) ($row->activo ?? true)],
            );

            $ctx->remember('medio_pago', $row->id, $medio->id);
        }
    }

    private function ensureDefaultMedios(LegacyImportContext $ctx): void
    {
        if ($ctx->dryRun) {
            return;
        }

        $defaults = [
            ['nombre' => 'Efectivo', 'tipo' => 'efectivo'],
            ['nombre' => 'Tarjeta de débito', 'tipo' => 'tarjeta_debito'],
            ['nombre' => 'Tarjeta de crédito', 'tipo' => 'tarjeta_credito'],
            ['nombre' => 'Transferencia', 'tipo' => 'transferencia'],
            ['nombre' => 'QR / Billetera virtual', 'tipo' => 'qr'],
            ['nombre' => 'Cuenta corriente', 'tipo' => 'cuenta_corriente'],
        ];

        foreach ($defaults as $medio) {
            MedioPago::firstOrCreate(
                ['empresa_id' => $ctx->empresaId, 'nombre' => $medio['nombre']],
                ['tipo' => $medio['tipo'], 'activo' => true],
            );
        }
    }
}
