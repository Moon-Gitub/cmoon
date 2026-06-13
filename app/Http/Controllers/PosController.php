<?php

namespace App\Http\Controllers;

use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\MedioPago;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PosController extends Controller
{
    public function index(Request $request): View
    {
        $usuario = auth()->user();
        $sucursal = $usuario->sucursal ?? Sucursal::where('activa', true)->first();

        $sesionAbierta = CajaSesion::with('caja')
            ->where('user_id', $usuario->id)
            ->where('estado', 'abierta')
            ->latest('abierta_at')
            ->first();

        // Precarga de un presupuesto pendiente en el carrito
        $presupuesto = null;
        $presupuestoItems = [];
        if ($request->filled('presupuesto')) {
            $presupuesto = \App\Models\Presupuesto::with('items.producto')
                ->where('estado', 'pendiente')
                ->find($request->integer('presupuesto'));

            $presupuestoItems = $presupuesto?->items->map(fn ($i) => [
                'producto_id' => $i->producto_id,
                'codigo' => $i->producto?->codigo ?? '',
                'nombre' => $i->descripcion,
                'cantidad' => (float) $i->cantidad,
                'precio' => (float) $i->precio_unitario,
                'iva' => (float) ($i->producto?->alicuota_iva ?? 21),
            ])->all() ?? [];
        }

        return view('pos.index', [
            'sucursal' => $sucursal,
            'sesionAbierta' => $sesionAbierta,
            'presupuesto' => $presupuesto,
            'presupuestoItems' => $presupuestoItems,
        ]);
    }

    public function catalogo(): JsonResponse
    {
        return response()->json([
            'productos' => Producto::where('activo', true)
                ->get(['id', 'codigo', 'nombre', 'precio_venta', 'alicuota_iva', 'unidad', 'pesable'])
                ->map(fn ($p) => [
                    'id' => $p->id,
                    'codigo' => $p->codigo,
                    'nombre' => $p->nombre,
                    'precio' => (float) $p->precio_venta,
                    'iva' => (float) $p->alicuota_iva,
                    'unidad' => $p->unidad,
                    'pesable' => $p->pesable,
                ]),
            'clientes' => Cliente::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'documento', 'lista_precio_id']),
            'listas' => ListaPrecio::where('activa', true)
                ->get(['id', 'nombre', 'porcentaje'])
                ->map(fn ($l) => [
                    'id' => $l->id,
                    'nombre' => $l->nombre,
                    'porcentaje' => (float) $l->porcentaje,
                ]),
            'medios' => MedioPago::where('activo', true)
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo', 'recargo_porcentaje'])
                ->map(fn ($m) => [
                    'id' => $m->id,
                    'nombre' => $m->nombre,
                    'tipo' => $m->tipo,
                    'recargo' => (float) $m->recargo_porcentaje,
                ]),
        ]);
    }

    public function guardar(Request $request, VentaService $ventaService): JsonResponse
    {
        $datos = $request->validate([
            'uuid' => ['required', 'uuid'],
            'presupuesto_id' => ['nullable', 'exists:presupuestos,id'],
            'sucursal_id' => ['required', 'exists:sucursales,id'],
            'caja_sesion_id' => ['nullable', 'exists:caja_sesiones,id'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'descuento' => ['nullable', 'numeric', 'min:0'],
            'recargo' => ['nullable', 'numeric', 'min:0'],
            'origen' => ['nullable', 'in:pos,offline'],
            'fecha' => ['nullable', 'date'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
            'items.*.alicuota_iva' => ['nullable', 'numeric'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.medio_pago_id' => ['required', 'exists:medios_pago,id'],
            'pagos.*.importe' => ['required', 'numeric', 'gt:0'],
        ]);

        $venta = $ventaService->crear($datos, auth()->id());

        if (! empty($datos['presupuesto_id'])) {
            \App\Models\Presupuesto::where('id', $datos['presupuesto_id'])
                ->where('estado', 'pendiente')
                ->update(['estado' => 'convertido', 'venta_id' => $venta->id]);
        }

        return response()->json([
            'id' => $venta->id,
            'numero' => $venta->numero,
            'total' => (float) $venta->total,
            'ticket_url' => route('ventas.ticket', $venta),
        ], 201);
    }
}
