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
            $empresaId = Empresa::value('id');

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
            $mediosCtaCte = MedioPago::where('tipo', 'cuenta_corriente')->pluck('id')->all();
            $importeCtaCte = 0.0;
            foreach ($datos['pagos'] as $pago) {
                if (in_array((int) $pago['medio_pago_id'], $mediosCtaCte, true)) {
                    $importeCtaCte += (float) $pago['importe'];
                }
            }
            if ($importeCtaCte > 0 && ! $cliente) {
                throw ValidationException::withMessages([
                    'cliente_id' => 'Para vender en cuenta corriente hay que seleccionar un cliente.',
                ]);
            }

            $numero = (int) Venta::where('empresa_id', $empresaId)->lockForUpdate()->max('numero') + 1;

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
                    $this->stockService->mover(
                        $item['producto'],
                        (int) $datos['sucursal_id'],
                        -$item['cantidad'],
                        'venta',
                        "Venta #{$numero}",
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
                    'tipo' => 'venta',
                    'concepto' => "Venta #{$numero} en cuenta corriente",
                    'importe' => round($importeCtaCte, 2),
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
     * Anula una venta: repone stock y revierte la cta. cte. si corresponde.
     */
    public function anular(Venta $venta, string $motivo, int $userId): Venta
    {
        if ($venta->estado === 'anulada') {
            return $venta;
        }

        return DB::transaction(function () use ($venta, $motivo, $userId) {
            foreach ($venta->items as $item) {
                if ($item->producto_id && $item->producto) {
                    $this->stockService->mover(
                        $item->producto,
                        $venta->sucursal_id,
                        (float) $item->cantidad,
                        'anulacion',
                        "Anulación venta #{$venta->numero}",
                        $venta,
                        $userId,
                    );
                }
            }

            $movimientoCta = MovimientoCuenta::where('referencia_type', $venta->getMorphClass())
                ->where('referencia_id', $venta->id)
                ->where('tipo', 'venta')
                ->first();

            if ($movimientoCta) {
                MovimientoCuenta::create([
                    'titular_type' => $movimientoCta->titular_type,
                    'titular_id' => $movimientoCta->titular_id,
                    'tipo' => 'ajuste',
                    'concepto' => "Anulación venta #{$venta->numero}",
                    'importe' => -(float) $movimientoCta->importe,
                    'referencia_type' => $venta->getMorphClass(),
                    'referencia_id' => $venta->id,
                    'user_id' => $userId,
                    'fecha' => now()->toDateString(),
                ]);
            }

            $venta->update([
                'estado' => 'anulada',
                'motivo_anulacion' => $motivo,
                'anulada_at' => now(),
                'anulada_por' => $userId,
            ]);

            return $venta;
        });
    }
}
