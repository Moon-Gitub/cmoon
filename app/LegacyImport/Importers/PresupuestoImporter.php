<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\LegacyImport\Support\LegacyJsonParser;
use App\Models\Presupuesto;
use App\Models\PresupuestoItem;
use Illuminate\Support\Str;

class PresupuestoImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'presupuestos';
    }

    public function label(): string
    {
        return 'Presupuestos';
    }

    public function import(LegacyImportContext $ctx): void
    {
        foreach ($ctx->legacy('presupuestos')->orderBy('id')->get() as $row) {
            if ($this->skipIfMapped($ctx, 'presupuesto', $row->id)) {
                continue;
            }

            $clienteId = $ctx->idMap->get('cliente', $row->id_cliente ?? 0);
            $userId = $ctx->idMap->get('user', $row->id_vendedor ?? 0) ?? $ctx->defaultUserId;

            if (! $userId) {
                $ctx->stats->inc('presupuesto', 'errors');
                continue;
            }

            $items = LegacyJsonParser::productos($row->productos ?? null);
            if ($items === []) {
                $ctx->stats->inc('presupuesto', 'errors');
                continue;
            }

            if ($ctx->dryRun) {
                $ctx->remember('presupuesto', $row->id, (int) $row->id);
                continue;
            }

            $presupuesto = Presupuesto::create([
                'uuid' => (string) Str::uuid(),
                'empresa_id' => $ctx->empresaId,
                'cliente_id' => $clienteId,
                'user_id' => $userId,
                'numero' => (int) $row->id,
                'estado' => $this->mapEstado($row->estado ?? 0),
                'origen' => 'web',
                'total' => (float) ($row->total ?? 0),
                'observaciones' => $row->observaciones ?? null,
                'fecha' => $this->parseDate($row->fecha ?? null) ?? now()->toDateString(),
            ]);

            foreach ($items as $item) {
                PresupuestoItem::create([
                    'presupuesto_id' => $presupuesto->id,
                    'producto_id' => $ctx->idMap->get('producto', $item['id_producto']),
                    'descripcion' => $item['descripcion'] ?: 'Producto #'.$item['id_producto'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio'],
                    'total' => $item['total'],
                ]);
            }

            $ctx->remember('presupuesto', $row->id, $presupuesto->id);
        }
    }

    private function mapEstado(mixed $legacy): string
    {
        return match ((int) $legacy) {
            1 => 'convertido',
            2 => 'anulado',
            default => 'pendiente',
        };
    }
}
