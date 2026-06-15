<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;
use App\LegacyImport\Support\LegacyJsonParser;
use App\LegacyImport\Support\MedioPagoResolver;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use Illuminate\Support\Str;

class VentaImporter extends AbstractImporter
{
    public function key(): string
    {
        return 'ventas';
    }

    public function label(): string
    {
        return 'Ventas, ítems y pagos';
    }

    public function import(LegacyImportContext $ctx): void
    {
        MedioPagoResolver::reset();

        $query = $ctx->legacy('ventas')->orderBy('id');

        if ($this->hasColumn($ctx, 'id_empresa')) {
            $query->where('id_empresa', $ctx->legacyEmpresaId);
        }

        $chunk = config('legacy.chunk_size', 200);

        $query->chunk($chunk, function ($rows) use ($ctx) {
            foreach ($rows as $row) {
                if ($this->skipIfMapped($ctx, 'venta', $row->id)) {
                    continue;
                }

                $clienteId = $ctx->idMap->get('cliente', $row->id_cliente ?? 0);
                $userId = $ctx->idMap->get('user', $row->id_vendedor ?? 0) ?? $ctx->defaultUserId;
                $sucursalId = $ctx->resolveSucursal($row->sucursal ?? 'stock') ?? $ctx->defaultSucursalId;

                if (! $userId || ! $sucursalId) {
                    $ctx->stats->inc('venta', 'errors');
                    continue;
                }

                $items = $this->resolveItems($ctx, (int) $row->id, $row->productos ?? null);
                if ($items === []) {
                    $ctx->stats->inc('venta', 'errors');
                    continue;
                }

                $subtotal = (float) ($row->neto ?? $row->total ?? 0);
                $total = (float) ($row->total ?? $subtotal);
                $uuid = Str::isUuid($row->uuid ?? '') ? $row->uuid : (string) Str::uuid();

                if ($ctx->dryRun) {
                    $ctx->remember('venta', $row->id, (int) $row->id);
                    continue;
                }

                $venta = Venta::create([
                    'uuid' => $uuid,
                    'empresa_id' => $ctx->empresaId,
                    'sucursal_id' => $sucursalId,
                    'cliente_id' => $clienteId,
                    'user_id' => $userId,
                    'numero' => (int) ($row->codigo ?? $row->id),
                    'estado' => $this->mapEstado($row->estado ?? 1),
                    'origen' => 'pos',
                    'subtotal' => $subtotal,
                    'descuento' => 0,
                    'recargo' => 0,
                    'total' => $total,
                    'fecha' => $this->parseDateTime($row->fecha ?? null) ?? now(),
                ]);

                foreach ($items as $item) {
                    $productoId = $ctx->idMap->get('producto', $item['id_producto']);

                    VentaItem::create([
                        'venta_id' => $venta->id,
                        'producto_id' => $productoId,
                        'descripcion' => $item['descripcion'] ?: 'Producto #'.$item['id_producto'],
                        'cantidad' => $item['cantidad'],
                        'precio_unitario' => $item['precio'],
                        'alicuota_iva' => 21,
                        'total' => $item['total'],
                    ]);
                }

                $this->importPagos($ctx, $venta->id, $row->metodo_pago ?? null, $total);

                $ctx->remember('venta', $row->id, $venta->id);
            }
        });
    }

    /** @return list<array<string, mixed>> */
    private function resolveItems(LegacyImportContext $ctx, int $ventaId, mixed $jsonFallback): array
    {
        if ($this->tableExists($ctx, 'productos_venta')) {
            $rows = $ctx->legacy('productos_venta')->where('id_venta', $ventaId)->get();
            if ($rows->isNotEmpty()) {
                $items = [];
                foreach ($rows as $pv) {
                    $items[] = [
                        'id_producto' => (int) $pv->id_producto,
                        'cantidad' => (float) $pv->cantidad,
                        'precio' => (float) ($pv->precio_venta ?? 0),
                        'descripcion' => '',
                        'total' => (float) $pv->cantidad * (float) ($pv->precio_venta ?? 0),
                    ];
                }

                return $items;
            }
        }

        return LegacyJsonParser::productos($jsonFallback);
    }

    private function importPagos(LegacyImportContext $ctx, int $ventaId, mixed $metodoPagoRaw, float $totalVenta): void
    {
        $pagos = LegacyJsonParser::metodosPago($metodoPagoRaw);

        if ($pagos === []) {
            $medioId = MedioPagoResolver::resolve($ctx->empresaId, 'efectivo');
            if ($medioId) {
                VentaPago::create([
                    'venta_id' => $ventaId,
                    'medio_pago_id' => $medioId,
                    'importe' => $totalVenta,
                ]);
            }

            return;
        }

        $suma = array_sum(array_column($pagos, 'importe'));
        if ($suma <= 0) {
            $pagos[0]['importe'] = $totalVenta;
        }

        foreach ($pagos as $pago) {
            $importe = (float) $pago['importe'];
            if ($importe <= 0) {
                continue;
            }

            $medioId = MedioPagoResolver::resolve($ctx->empresaId, $pago['tipo']);
            if (! $medioId) {
                continue;
            }

            VentaPago::create([
                'venta_id' => $ventaId,
                'medio_pago_id' => $medioId,
                'importe' => $importe,
            ]);
        }
    }

    private function mapEstado(mixed $legacy): string
    {
        return 'completada';
    }

    private function hasColumn(LegacyImportContext $ctx, string $column): bool
    {
        return $ctx->legacy('information_schema.columns')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', 'ventas')
            ->where('COLUMN_NAME', $column)
            ->exists();
    }

    private function tableExists(LegacyImportContext $ctx, string $table): bool
    {
        return $ctx->legacy('information_schema.tables')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->exists();
    }
}
