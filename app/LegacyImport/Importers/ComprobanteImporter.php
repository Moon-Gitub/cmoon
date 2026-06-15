<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Cliente;
use App\Models\Comprobante;

class ComprobanteImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'comprobantes';
    }

    public function label(): string
    {
        return 'Comprobantes AFIP (CAE histórico)';
    }

    public function import(LegacyImportContext $ctx): void
    {
        if (! $ctx->defaultEmisorId || ! $this->tableExists($ctx, 'ventas_factura')) {
            return;
        }

        $defaultPv = reset($ctx->puntoVentaMap) ?: null;
        if (! $defaultPv) {
            return;
        }

        foreach ($ctx->legacy('ventas_factura')->orderBy('id')->get() as $vf) {
            if (empty($vf->id_venta) || empty($vf->cae)) {
                continue;
            }

            if ($this->skipIfMapped($ctx, 'comprobante', $vf->id)) {
                continue;
            }

            $ventaId = $ctx->idMap->get('venta', $vf->id_venta);
            if (! $ventaId) {
                $ctx->stats->inc('comprobante', 'errors');
                continue;
            }

            $venta = $ctx->legacy('ventas')->where('id', $vf->id_venta)->first();
            if (! $venta) {
                continue;
            }

            $ptoVta = (int) ($venta->pto_vta ?? array_key_first($ctx->puntoVentaMap) ?? 1);
            $puntoVentaId = $ctx->puntoVentaMap[$ptoVta] ?? $defaultPv;

            $cliente = null;
            if ($venta->id_cliente) {
                $clienteId = $ctx->idMap->get('cliente', $venta->id_cliente);
                $cliente = $clienteId ? Cliente::find($clienteId) : null;
            }

            if ($ctx->dryRun) {
                $ctx->remember('comprobante', $vf->id, (int) $vf->id);
                continue;
            }

            Comprobante::create([
                'venta_id' => $ventaId,
                'emisor_id' => $ctx->defaultEmisorId,
                'punto_venta_id' => $puntoVentaId,
                'user_id' => $ctx->idMap->get('user', $venta->id_vendedor ?? 0) ?? $ctx->defaultUserId,
                'tipo_comprobante' => (int) ($venta->cbte_tipo ?? 6),
                'numero' => (int) ($vf->nro_cbte ?? 0),
                'doc_tipo' => 99,
                'doc_numero' => $cliente?->documento ?? '0',
                'receptor_nombre' => $cliente?->nombre,
                'receptor_condicion_iva' => $cliente?->condicion_iva,
                'neto' => (float) ($venta->neto ?? 0),
                'iva' => (float) ($venta->impuesto ?? 0),
                'total' => (float) ($venta->total ?? 0),
                'cae' => $vf->cae,
                'cae_vencimiento' => $this->parseDate($vf->fec_vto_cae ?? null),
                'estado' => 'autorizado',
                'respuesta_afip' => $venta->respuesta_afip ? json_decode($venta->respuesta_afip, true) : null,
                'fecha_emision' => $this->parseDate($vf->fec_factura ?? $venta->fecha) ?? now()->toDateString(),
            ]);

            $ctx->remember('comprobante', $vf->id, $ventaId);
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
