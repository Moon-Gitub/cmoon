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
        $this->ensureDefaults($ctx);

        $query = $ctx->legacy('ventas')->orderBy('id');

        // Importar todas las ventas del dump (multi-empresa legacy → un tenant).
        // Filtrar solo si LEGACY_FILTER_EMPRESA=true.
        if (filter_var(env('LEGACY_FILTER_EMPRESA', false), FILTER_VALIDATE_BOOL)
            && $this->columnExists($ctx, 'ventas', 'id_empresa')) {
            $query->where('id_empresa', $ctx->legacyEmpresaId);
        }

        $desde = config('legacy.ventas_desde');
        $hasta = config('legacy.ventas_hasta');
        if (is_string($desde) && $desde !== '') {
            $query->where('fecha', '>=', $desde);
        }
        if (is_string($hasta) && $hasta !== '') {
            $query->where('fecha', '<=', $hasta);
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
                $tipo = $this->mapTipo($row);

                if ($ctx->dryRun) {
                    $ctx->remember('venta', $row->id, (int) $row->id);
                    continue;
                }

                // Preferir id legacy; si ya existe (imports previos usaban `codigo`),
                // desplazar para no chocar con ventas.empresa_id+numero unique.
                $numero = (int) $row->id;
                if (Venta::query()
                    ->where('empresa_id', $ctx->empresaId)
                    ->where('numero', $numero)
                    ->exists()) {
                    $numero = 200_000_000 + (int) $row->id;
                }

                try {
                    $venta = Venta::create([
                        'uuid' => $uuid,
                        'empresa_id' => $ctx->empresaId,
                        'sucursal_id' => $sucursalId,
                        'cliente_id' => $clienteId,
                        'user_id' => $userId,
                        'numero' => $numero,
                        'estado' => $this->mapEstado($row->estado ?? 1),
                        'origen' => 'pos',
                        'tipo' => $tipo,
                        'subtotal' => $subtotal,
                        'descuento' => 0,
                        'recargo' => 0,
                        'total' => $total,
                        'fecha' => $this->parseDateTime($row->fecha ?? null) ?? now(),
                    ]);
                } catch (\Illuminate\Database\UniqueConstraintViolationException $e) {
                    $numero = 200_000_000 + (int) $row->id;
                    $venta = Venta::create([
                        'uuid' => $uuid,
                        'empresa_id' => $ctx->empresaId,
                        'sucursal_id' => $sucursalId,
                        'cliente_id' => $clienteId,
                        'user_id' => $userId,
                        'numero' => $numero,
                        'estado' => $this->mapEstado($row->estado ?? 1),
                        'origen' => 'pos',
                        'tipo' => $tipo,
                        'subtotal' => $subtotal,
                        'descuento' => 0,
                        'recargo' => 0,
                        'total' => $total,
                        'fecha' => $this->parseDateTime($row->fecha ?? null) ?? now(),
                    ]);
                }

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

    private function ensureDefaults(LegacyImportContext $ctx): void
    {
        if ($ctx->defaultUserId === null) {
            $ctx->defaultUserId = \App\Models\User::query()
                ->where('empresa_id', $ctx->empresaId)
                ->orderBy('id')
                ->value('id');
        }

        if ($ctx->defaultSucursalId === null) {
            $sucursales = \App\Models\Sucursal::query()
                ->where('empresa_id', $ctx->empresaId)
                ->orderBy('id')
                ->get(['id', 'codigo', 'nombre']);

            foreach ($sucursales as $sucursal) {
                if ($sucursal->codigo) {
                    $ctx->sucursalMap[$sucursal->codigo] ??= $sucursal->id;
                }
                $ctx->sucursalMap['stock'] ??= $sucursal->id;
                $ctx->sucursalMap['stkProd'] ??= $sucursal->id;
            }

            $ctx->defaultSucursalId = $sucursales->first()?->id;
        }
    }

    private function mapEstado(mixed $legacy): string
    {
        return 'completada';
    }

    private function mapTipo(object $row): string
    {
        $cbte = 0;
        if (isset($row->cbte_tipo) && is_numeric($row->cbte_tipo)) {
            $cbte = (int) $row->cbte_tipo;
        } elseif (isset($row->codigo) && is_numeric($row->codigo)) {
            $cbte = (int) $row->codigo;
        }

        return $cbte === 999 ? Venta::TIPO_DEVOLUCION : Venta::TIPO_VENTA;
    }
}
