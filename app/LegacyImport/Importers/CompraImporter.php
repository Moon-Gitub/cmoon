<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\LegacyImport\Support\LegacyJsonParser;
use App\Models\Compra;
use App\Models\CompraItem;

class CompraImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'compras';
    }

    public function label(): string
    {
        return 'Compras a proveedores';
    }

    public function import(LegacyImportContext $ctx): void
    {
        foreach ($ctx->legacy('compras')->orderBy('id')->get() as $row) {
            if ($this->skipIfMapped($ctx, 'compra', $row->id)) {
                continue;
            }

            $proveedorId = $ctx->idMap->get('proveedor', $row->id_proveedor ?? 0);
            $userId = $ctx->defaultUserId;
            $sucursalId = $ctx->resolveSucursal($row->sucursalDestino ?? 'stock') ?? $ctx->defaultSucursalId;

            if (! $proveedorId || ! $userId || ! $sucursalId) {
                $ctx->stats->inc('compra', 'errors');
                continue;
            }

            $items = LegacyJsonParser::productos($row->productos ?? null);
            if ($items === []) {
                $ctx->stats->inc('compra', 'errors');
                continue;
            }

            $total = (float) ($row->total ?? 0);
            if ($total <= 0 && is_numeric(str_replace(',', '.', (string) $row->total))) {
                $total = (float) str_replace(',', '.', (string) $row->total);
            }

            if ($ctx->dryRun) {
                $ctx->remember('compra', $row->id, (int) $row->id);
                continue;
            }

            $compra = Compra::create([
                'empresa_id' => $ctx->empresaId,
                'sucursal_id' => $sucursalId,
                'proveedor_id' => $proveedorId,
                'user_id' => $userId,
                'factura_numero' => $row->numeroFactura ?? null,
                'condicion' => ((int) ($row->medioPago ?? 0)) === 0 ? 'contado' : 'cuenta_corriente',
                'total' => $total,
                'estado' => 'completada',
                'observaciones' => $row->observacion ?? null,
                'fecha' => $this->parseDate($row->fecha ?? null) ?? now()->toDateString(),
            ]);

            foreach ($items as $item) {
                CompraItem::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $ctx->idMap->get('producto', $item['id_producto']),
                    'descripcion' => $item['descripcion'] ?: 'Producto #'.$item['id_producto'],
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['precio_compra'] ?: $item['precio'],
                    'total' => $item['total'],
                ]);
            }

            $ctx->remember('compra', $row->id, $compra->id);
        }
    }
}
