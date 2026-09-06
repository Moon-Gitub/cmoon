<?php

namespace App\Services;

use App\Models\MedioPago;
use App\Models\Presupuesto;
use App\Models\Remito;
use App\Models\RemitoItem;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use InvalidArgumentException;

class RemitoService
{
    private const ESTADOS_ORIGEN_PRESUPUESTO = ['pedido', 'aprobado', 'pendiente'];

    public function __construct(private VentaService $ventaService) {}

    public function crearDesdePresupuesto(Presupuesto $presupuesto): Remito
    {
        $presupuesto->loadMissing('items');

        if ($presupuesto->items->isEmpty()) {
            throw new InvalidArgumentException('El presupuesto no tiene ítems.');
        }

        if (! in_array($presupuesto->estado, self::ESTADOS_ORIGEN_PRESUPUESTO, true)) {
            throw new InvalidArgumentException(
                'Solo se puede emitir remito desde presupuestos en estado pedido, aprobado o pendiente.'
            );
        }

        return DB::transaction(function () use ($presupuesto) {
            $empresaId = (int) $presupuesto->empresa_id;
            $numero = (int) Remito::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->max('numero') + 1;

            $remito = Remito::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => auth()->user()?->sucursal_id,
                'cliente_id' => $presupuesto->cliente_id,
                'presupuesto_id' => $presupuesto->id,
                'venta_id' => $presupuesto->venta_id,
                'user_id' => auth()->id() ?? $presupuesto->user_id,
                'numero' => $numero,
                'estado' => 'emitido',
                'fecha' => now()->toDateString(),
                'observaciones' => $presupuesto->observaciones,
            ]);

            foreach ($presupuesto->items as $item) {
                RemitoItem::create([
                    'remito_id' => $remito->id,
                    'producto_id' => $item->producto_id,
                    'descripcion' => $item->descripcion,
                    'cantidad' => $item->cantidad,
                ]);
            }

            return $remito->load('items');
        });
    }

    public function marcarEntregado(Remito $remito): Remito
    {
        if ($remito->estado === 'anulado') {
            throw new InvalidArgumentException('El remito está anulado.');
        }

        $remito->update(['estado' => 'entregado']);

        return $remito->fresh('items');
    }

    /**
     * Crea una venta a partir de los ítems del remito (precios del presupuesto o del producto).
     * Vincula remito.venta_id y marca el presupuesto como convertido si aplica.
     */
    public function convertirAVenta(Remito $remito): \App\Models\Venta
    {
        if ($remito->estado === 'anulado') {
            throw new InvalidArgumentException('El remito está anulado.');
        }

        if ($remito->venta_id) {
            throw new InvalidArgumentException('El remito ya tiene una venta asociada.');
        }

        $remito->loadMissing(['items.producto', 'presupuesto.items', 'cliente']);

        if ($remito->items->isEmpty()) {
            throw new InvalidArgumentException('El remito no tiene ítems.');
        }

        $sucursalId = $remito->sucursal_id
            ?? auth()->user()?->sucursal_id
            ?? null;

        if (! $sucursalId) {
            throw new InvalidArgumentException('No hay sucursal para registrar la venta.');
        }

        $items = [];
        foreach ($remito->items as $item) {
            $precio = $this->precioDesdePresupuesto($remito, $item);
            if ($precio === null) {
                $precio = (float) ($item->producto?->precio_venta ?? 0);
            }

            $items[] = [
                'producto_id' => $item->producto_id,
                'descripcion' => $item->descripcion,
                'cantidad' => (float) $item->cantidad,
                'precio_unitario' => $precio,
                'alicuota_iva' => (float) ($item->producto?->alicuota_iva ?? 21),
            ];
        }

        $total = round(array_sum(array_map(
            fn ($i) => round($i['cantidad'] * $i['precio_unitario'], 2),
            $items
        )), 2);

        $medio = $this->resolverMedioPago((bool) $remito->cliente_id);
        if (! $medio) {
            throw new InvalidArgumentException('No hay un medio de pago activo para registrar la venta.');
        }

        if ($medio->esCuentaCorriente() && ! $remito->cliente_id) {
            throw new InvalidArgumentException('Para cuenta corriente el remito debe tener cliente.');
        }

        $venta = $this->ventaService->crear([
            'uuid' => (string) Str::uuid(),
            'sucursal_id' => (int) $sucursalId,
            'cliente_id' => $remito->cliente_id,
            'origen' => 'remito',
            'items' => $items,
            'pagos' => [[
                'medio_pago_id' => $medio->id,
                'importe' => $total,
            ]],
        ], auth()->id() ?? (int) $remito->user_id);

        $remito->update([
            'venta_id' => $venta->id,
            'estado' => $remito->estado === 'emitido' ? 'entregado' : $remito->estado,
        ]);

        if ($remito->presupuesto_id) {
            Presupuesto::where('id', $remito->presupuesto_id)
                ->whereIn('estado', ['pendiente', 'aprobado', 'pedido'])
                ->update(['estado' => 'convertido', 'venta_id' => $venta->id]);
        }

        return $venta;
    }

    private function precioDesdePresupuesto(Remito $remito, RemitoItem $item): ?float
    {
        $presupuesto = $remito->presupuesto;
        if (! $presupuesto || $presupuesto->items->isEmpty()) {
            return null;
        }

        $match = null;
        if ($item->producto_id) {
            $match = $presupuesto->items->first(
                fn ($i) => (int) $i->producto_id === (int) $item->producto_id
            );
        }

        $match ??= $presupuesto->items->first(
            fn ($i) => $i->descripcion === $item->descripcion
        );

        return $match !== null ? (float) $match->precio_unitario : null;
    }

    private function resolverMedioPago(bool $conCliente): ?MedioPago
    {
        if ($conCliente) {
            $cta = MedioPago::where('activo', true)
                ->where('tipo', 'cuenta_corriente')
                ->orderBy('id')
                ->first();
            if ($cta) {
                return $cta;
            }
        }

        return MedioPago::where('activo', true)
            ->where('tipo', 'efectivo')
            ->orderBy('id')
            ->first()
            ?? MedioPago::where('activo', true)->orderBy('id')->first();
    }
}
