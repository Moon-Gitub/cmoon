<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Services\VentaService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function index(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfDay();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        $filtradas = $this->filtrarVentas(Venta::query(), $request, $desde, $hasta);

        $ventas = (clone $filtradas)
            ->with(['cliente', 'vendedor', 'pagos.medioPago'])
            ->withExists(['comprobantes as facturada' => fn ($q) => $q->whereIn('estado', ['autorizado', 'pendiente'])])
            ->orderByDesc('fecha')
            ->paginate(25)
            ->withQueryString();

        $acumulado = (clone $filtradas);
        if ($request->input('estado') !== 'anulada') {
            $acumulado->where('estado', 'completada');
        }
        $totalPeriodo = (float) $acumulado->sum('total');
        $cantidadPeriodo = (clone $acumulado)->count();

        $porMedio = VentaPago::query()
            ->join('medios_pago', 'medios_pago.id', '=', 'venta_pagos.medio_pago_id')
            ->whereIn('venta_pagos.venta_id', (clone $acumulado)->select('id'))
            ->select('medios_pago.nombre', DB::raw('SUM(venta_pagos.importe) as total'))
            ->groupBy('medios_pago.nombre')
            ->orderByDesc('total')
            ->get();

        $emisores = auth()->user()->can('facturacion.emitir')
            ? \App\Models\Emisor::with(['puntosVenta' => fn ($q) => $q->where('activo', true)])
                ->where('activo', true)
                ->orderBy('razon_social')
                ->get()
            : collect();

        return view('ventas.index', [
            'ventas' => $ventas,
            'totalPeriodo' => $totalPeriodo,
            'cantidadPeriodo' => $cantidadPeriodo,
            'porMedio' => $porMedio,
            'mediosPago' => MedioPago::query()->orderByRaw("CASE WHEN tipo = 'efectivo' THEN 0 ELSE 1 END")->orderBy('nombre')->get(),
            'desde' => $desde,
            'hasta' => $hasta,
            'emisores' => $emisores,
        ]);
    }

    private function filtrarVentas(Builder $query, Request $request, $desde, $hasta): Builder
    {
        $query->whereBetween('fecha', [$desde, $hasta]);

        $estado = (string) $request->input('estado', '');
        if ($estado === 'facturada') {
            $query->where('estado', 'completada')
                ->whereHas('comprobantes', fn ($q) => $q->whereIn('estado', ['autorizado', 'pendiente']));
        } elseif ($estado === 'completada') {
            $query->where('estado', 'completada')
                ->whereDoesntHave('comprobantes', fn ($q) => $q->whereIn('estado', ['autorizado', 'pendiente']));
        } elseif ($estado === 'anulada') {
            $query->where('estado', 'anulada');
        }

        if ($request->filled('medio_pago_id')) {
            $query->whereHas('pagos', fn ($q) => $q->where('medio_pago_id', $request->integer('medio_pago_id')));
        }

        return $query;
    }

    public function show(Venta $venta): View
    {
        return view('ventas.show', [
            'venta' => $venta->load(['items.producto', 'pagos.medioPago', 'cliente', 'vendedor', 'sucursal', 'anuladaPor']),
            'comprobante' => \App\Models\Comprobante::with('puntoVenta')
                ->where('venta_id', $venta->id)
                ->whereIn('estado', ['autorizado', 'pendiente'])
                ->first(),
            'emisores' => auth()->user()->can('facturacion.emitir')
                ? \App\Models\Emisor::with('puntosVenta')->where('activo', true)->get()
                : collect(),
        ]);
    }

    public function anular(Request $request, Venta $venta, VentaService $ventaService): RedirectResponse
    {
        abort_unless(auth()->user()->can('ventas.anular'), 403);

        $datos = $request->validate([
            'motivo' => ['required', 'string', 'max:255'],
        ]);

        $ventaService->anular($venta, $datos['motivo'], auth()->id());

        return back()->with('ok', "Venta #{$venta->numero} anulada. Stock repuesto. Revisá la nota de impacto en caja en el motivo.");
    }

    public function editarFecha(Request $request, Venta $venta, VentaService $ventaService): RedirectResponse
    {
        abort_unless(auth()->user()->can('ventas.editar_fecha'), 403);

        $datos = $request->validate([
            'fecha' => ['required', 'date'],
        ]);

        $ventaService->cambiarFecha($venta, $datos['fecha'], auth()->id());

        return back()->with('ok', "Fecha de la venta #{$venta->numero} actualizada.");
    }

    public function ticket(Venta $venta): View
    {
        return view('ventas.ticket', [
            'venta' => $venta->load(['items', 'pagos.medioPago', 'cliente', 'vendedor', 'sucursal']),
            'empresa' => auth()->user()->empresa,
        ]);
    }
}
