<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Cliente;
use App\Models\MovimientoCuenta;
use App\Models\Venta;

class ClienteCuentaCorrienteImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'cc_clientes';
    }

    public function label(): string
    {
        return 'Cuenta corriente de clientes';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $this->tableExists($ctx, 'clientes_cuenta_corriente')) {
            return;
        }

        $chunk = config('legacy.chunk_size', 200);

        $ctx->legacy('clientes_cuenta_corriente')->orderBy('id')->chunk($chunk, function ($rows) use ($ctx) {
            foreach ($rows as $row) {
                if ($this->skipIfMapped($ctx, 'cc_cliente', $row->id)) {
                    continue;
                }

                $clienteId = $ctx->idMap->get('cliente', $row->id_cliente ?? 0);
                if (! $clienteId) {
                    $ctx->stats->inc('cc_cliente', 'errors');
                    continue;
                }

                $cliente = Cliente::find($clienteId);
                if (! $cliente) {
                    continue;
                }

                $tipoLegacy = (int) ($row->tipo ?? 0);
                $importe = (float) ($row->importe ?? 0);
                if ($importe == 0.0) {
                    $ctx->stats->inc('cc_cliente', 'skipped');
                    continue;
                }

                // Legacy: 0=cargo, 1=pago. CMoon: positivo deuda, negativo pago.
                if ($tipoLegacy === 1) {
                    $importe = -abs($importe);
                    $tipo = 'pago';
                } else {
                    $importe = abs($importe);
                    $tipo = 'venta';
                }

                $ventaId = $row->id_venta ? $ctx->idMap->get('venta', $row->id_venta) : null;

                if ($ctx->dryRun) {
                    $ctx->remember('cc_cliente', $row->id, (int) $row->id);
                    continue;
                }

                MovimientoCuenta::create([
                    'titular_type' => $cliente->getMorphClass(),
                    'titular_id' => $cliente->id,
                    'tipo' => $tipo,
                    'concepto' => $row->descripcion ?: 'Import legacy CC',
                    'importe' => $importe,
                    'referencia_type' => $ventaId ? (new Venta)->getMorphClass() : null,
                    'referencia_id' => $ventaId,
                    'user_id' => $ctx->defaultUserId,
                    'fecha' => $this->parseDate($row->fecha ?? null) ?? now()->toDateString(),
                ]);

                $ctx->remember('cc_cliente', $row->id, $clienteId);
            }
        });
    }

    private function tableExists(LegacyImportContext $ctx, string $table): bool
    {
        return $ctx->legacy('information_schema.tables')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->exists();
    }
}
