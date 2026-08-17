<?php

namespace App\Services;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\MedioPago;
use App\Models\MovimientoCuenta;
use App\Models\Producto;
use App\Models\Venta;
use App\Models\VentaItem;
use App\Models\VentaPago;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class VentaService
{
    public function __construct(private StockService $stockService) {}

    /**
     * Registra una venta completa de forma atómica.
     * Idempotente por UUID: si ya existe, devuelve la venta original
     * (clave para la sincronización offline).
     *
     * Estructura esperada de $datos:
     *  uuid, sucursal_id, caja_sesion_id?, cliente_id?, descuento?, origen?, fecha?
     *  tipo?: venta|devolucion, es_devolucion?: bool, venta_origen_numero?: int
     *  items: [{producto_id?, descripcion?, cantidad, precio_unitario, alicuota_iva?}]
     *  pagos: [{medio_pago_id, importe}]
     */
    public function crear(array $datos, int $userId): Venta
    {
        $existente = Venta::where('uuid', $datos['uuid'])->first();
        if ($existente) {
            return $existente;
        }

        return DB::transaction(function () use ($datos, $userId) {
            $empresaId = auth()->user()->empresa_id;
            $esDevolucion = $this->esDevolucion($datos);

            // Ítems: el total se calcula siempre del lado del servidor
            $items = [];
            $subtotal = 0.0;

            foreach ($datos['items'] as $item) {
                $producto = isset($item['producto_id'])
                    ? Producto::find($item['producto_id'])
                    : null;

                $cantidad = (float) $item['cantidad'];
                $precio = (float) $item['precio_unitario'];
                $totalItem = round($cantidad * $precio, 2);
                $subtotal += $totalItem;

                $items[] = [
                    'producto' => $producto,
                    'descripcion' => $item['descripcion'] ?? $producto?->nombre ?? 'Ítem',
                    'cantidad' => $cantidad,
                    'precio_unitario' => $precio,
                    'alicuota_iva' => (float) ($item['alicuota_iva'] ?? $producto?->alicuota_iva ?? 21),
                    'total' => $totalItem,
                ];
            }

            $subtotal = round($subtotal, 2);
            $descuento = round((float) ($datos['descuento'] ?? 0), 2);
            $recargo = round((float) ($datos['recargo'] ?? 0), 2);
            $total = round($subtotal - $descuento + $recargo, 2);

            $sumaPagos = round(array_sum(array_map(fn ($p) => (float) $p['importe'], $datos['pagos'])), 2);
            if (abs($sumaPagos - $total) > 0.01) {
                throw ValidationException::withMessages([
                    'pagos' => "La suma de los pagos ($ {$sumaPagos}) no coincide con el total ($ {$total}).",
                ]);
            }

            $cliente = isset($datos['cliente_id']) && $datos['cliente_id']
                ? Cliente::find($datos['cliente_id'])
                : null;

            // Pago en cta. cte. exige cliente identificado
            $importeCtaCte = 0.0;
            foreach ($datos['pagos'] as $pago) {
                $medio = MedioPago::find($pago['medio_pago_id']);
                if ($medio?->esCuentaCorriente()) {
                    $importeCtaCte += (float) $pago['importe'];
                }
            }
            if ($importeCtaCte > 0 && ! $cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => $esDevolucion
                        ? 'Para devolver en cuenta corriente hay que seleccionar un cliente.'
                        : 'Para vender en cuenta corriente hay que seleccionar un cliente.',
                ]);
            }

            if ($esDevolucion) {
                foreach ($datos['pagos'] as $pago) {
                    $medio = MedioPago::find($pago['medio_pago_id']);
                    if ($medio?->tipo === 'qr') {
                        throw ValidationException::withMessages([
                            'pagos' => 'La Devolución X no admite cobro por QR.',
                        ]);
                    }
                }
            }

            $numero = (int) Venta::where('empresa_id', $empresaId)->lockForUpdate()->max('numero') + 1;
            [$ventaOrigenId, $ventaOrigenNumero] = $this->resolverVentaOrigen($datos, $empresaId);
            $rotulo = $esDevolucion ? "Devolución #{$numero}" : "Venta #{$numero}";

            $venta = Venta::create([
                'uuid' => $datos['uuid'],
                'empresa_id' => $empresaId,
                'sucursal_id' => $datos['sucursal_id'],
                'caja_sesion_id' => $datos['caja_sesion_id'] ?? null,
                'cliente_id' => $cliente?->id,
                'user_id' => $userId,
                'numero' => $numero,
                'estado' => 'completada',
                'origen' => $datos['origen'] ?? 'pos',
                'tipo' => $esDevolucion ? Venta::TIPO_DEVOLUCION : Venta::TIPO_VENTA,
                'venta_origen_id' => $ventaOrigenId,
                'venta_origen_numero' => $ventaOrigenNumero,
                'subtotal' => $subtotal,
                'descuento' => $descuento,
                'recargo' => $recargo,
                'total' => $total,
                'fecha' => $datos['fecha'] ?? now(),
            ]);

            foreach ($items as $item) {
                VentaItem::create([
                    'venta_id' => $venta->id,
                    'producto_id' => $item['producto']?->id,
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'alicuota_iva' => $item['alicuota_iva'],
                    'total' => $item['total'],
                ]);

                if ($item['producto']) {
                    $this->moverStockVenta(
                        $item['producto'],
                        (int) $datos['sucursal_id'],
                        $esDevolucion ? $item['cantidad'] : -$item['cantidad'],
                        $esDevolucion ? 'devolucion' : 'venta',
                        $rotulo,
                        $venta,
                        $userId,
                    );
                }
            }

            foreach ($datos['pagos'] as $pago) {
                VentaPago::create([
                    'venta_id' => $venta->id,
                    'medio_pago_id' => $pago['medio_pago_id'],
                    'importe' => round((float) $pago['importe'], 2),
                ]);
            }

            if ($importeCtaCte > 0) {
                MovimientoCuenta::create([
                    'titular_type' => $cliente->getMorphClass(),
                    'titular_id' => $cliente->id,
                    'tipo' => $esDevolucion ? 'devolucion' : 'venta',
                    'concepto' => $esDevolucion
                        ? "Devolución #{$numero}"
                        : "Venta #{$numero} en cuenta corriente",
                    'importe' => $esDevolucion
                        ? -round($importeCtaCte, 2)
                        : round($importeCtaCte, 2),
                    'referencia_type' => $venta->getMorphClass(),
                    'referencia_id' => $venta->id,
                    'user_id' => $userId,
                    'fecha' => now()->toDateString(),
                ]);
            }

            return $venta;
        });
    }

    /**
     * Mueve stock por venta/anulación. Si el producto es un combo,
     * el movimiento se aplica sobre sus componentes.
     */
    private function moverStockVenta(
        \App\Models\Producto $producto,
        int $sucursalId,
        float $cantidad,
        string $tipo,
        string $observacion,
        Venta $venta,
        int $userId,
    ): void {
        if ($producto->es_combo) {
            foreach ($producto->componentes()->with('componente')->get() as $componente) {
                if ($componente->componente) {
                    $this->stockService->mover(
                        $componente->componente,
                        $sucursalId,
                        $cantidad * (float) $componente->cantidad,
                        $tipo,
                        "{$observacion} (combo {$producto->nombre})",
                        $venta,
                        $userId,
                    );
                }
            }

            return;
        }

        $this->stockService->mover($producto, $sucursalId, $cantidad, $tipo, $observacion, $venta, $userId);
    }

    /**
     * Anula una venta: repone stock y revierte la cta. cte. si corresponde.
     *
     * Impacto en caja (decisión producto 5/8/2026 — Carlos Carrasco):
     * - Al marcar estado=anulada, deja de contar en efectivoEsperado() de SU sesión.
     * - NO se crea CajaMovimiento de egreso en la caja abierta de hoy si la venta
     *   es de otra fecha (evita restar efectivo de la caja actual por anulaciones viejas).
     * - Si la venta es del mismo día calendario que la apertura de su sesión (timezone app),
     *   el impacto es implícito vía exclusión de ventas anuladas en esa sesión.
     */
    public function anular(Venta $venta, string $motivo, int $userId): Venta
    {
        if ($venta->estado === 'anulada') {
            return $venta;
        }

        return DB::transaction(function () use ($venta, $motivo, $userId) {
            $esDevolucion = $venta->esDevolucion();
            $rotuloAnulacion = $esDevolucion
                ? "Anulación devolución #{$venta->numero}"
                : "Anulación venta #{$venta->numero}";

            foreach ($venta->items as $item) {
                if ($item->producto_id && $item->producto) {
                    $this->moverStockVenta(
                        $item->producto,
                        $venta->sucursal_id,
                        $esDevolucion ? -(float) $item->cantidad : (float) $item->cantidad,
                        'anulacion',
                        $rotuloAnulacion,
                        $venta,
                        $userId,
                    );
                }
            }

            $movimientoCta = MovimientoCuenta::where('referencia_type', $venta->getMorphClass())
                ->where('referencia_id', $venta->id)
                ->whereIn('tipo', ['venta', 'devolucion'])
                ->first();

            if ($movimientoCta) {
                MovimientoCuenta::create([
                    'titular_type' => $movimientoCta->titular_type,
                    'titular_id' => $movimientoCta->titular_id,
                    'tipo' => 'ajuste',
                    'concepto' => $rotuloAnulacion,
                    'importe' => -(float) $movimientoCta->importe,
                    'referencia_type' => $venta->getMorphClass(),
                    'referencia_id' => $venta->id,
                    'user_id' => $userId,
                    'fecha' => now()->toDateString(),
                ]);
            }

            $notaCaja = $this->notaImpactoCajaAnulacion($venta);
            $motivoFinal = $notaCaja ? \Illuminate\Support\Str::limit(trim($motivo.' | '.$notaCaja), 250) : $motivo;

            $venta->update([
                'estado' => 'anulada',
                'motivo_anulacion' => $motivoFinal,
                'anulada_at' => now(),
                'anulada_por' => $userId,
            ]);

            return $venta;
        });
    }

    /**
     * Documenta si la anulación afecta o no el efectivo de la sesión asociada.
     */
    private function notaImpactoCajaAnulacion(Venta $venta): ?string
    {
        if (! $venta->caja_sesion_id) {
            return 'Sin sesión de caja asociada (sin impacto en caja).';
        }

        $sesion = $venta->cajaSesion;
        if (! $sesion) {
            return null;
        }

        $diaVenta = $venta->fecha->timezone(config('app.timezone'))->toDateString();
        $diaApertura = $sesion->abierta_at->timezone(config('app.timezone'))->toDateString();

        if ($diaVenta === $diaApertura) {
            return 'Caja: misma fecha sesión #'.$sesion->id;
        }

        return 'Sin egreso caja hoy (venta '.$diaVenta.' ≠ apertura '.$diaApertura.')';
    }

    /**
     * Cambia la fecha de una venta (solo con permiso ventas.editar_fecha / admin).
     */
    public function cambiarFecha(Venta $venta, string $fecha, int $userId): Venta
    {
        if ($venta->estado === 'anulada') {
            throw ValidationException::withMessages(['fecha' => 'No se puede cambiar la fecha de una venta anulada.']);
        }

        $anterior = $venta->fecha?->toDateTimeString();
        $venta->update(['fecha' => $fecha]);

        \Illuminate\Support\Facades\Log::info('venta.editar_fecha', [
            'venta_id' => $venta->id,
            'numero' => $venta->numero,
            'fecha_anterior' => $anterior,
            'fecha_nueva' => $fecha,
            'user_id' => $userId,
        ]);

        return $venta->fresh();
    }

    private function esDevolucion(array $datos): bool
    {
        if (! empty($datos['es_devolucion'])) {
            return true;
        }

        return ($datos['tipo'] ?? Venta::TIPO_VENTA) === Venta::TIPO_DEVOLUCION;
    }

    /**
     * @return array{0: int|null, 1: int|null}
     */
    private function resolverVentaOrigen(array $datos, int $empresaId): array
    {
        $numero = isset($datos['venta_origen_numero']) && $datos['venta_origen_numero'] !== ''
            ? (int) $datos['venta_origen_numero']
            : 0;

        if ($numero <= 0) {
            return [null, null];
        }

        $origen = Venta::query()
            ->where('empresa_id', $empresaId)
            ->where('numero', $numero)
            ->where('tipo', Venta::TIPO_VENTA)
            ->first();

        return [$origen?->id, $numero];
    }
}
