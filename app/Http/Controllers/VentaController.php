<?php

namespace App\Http\Controllers;

use App\Models\MedioPago;
use App\Models\Venta;
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
        $estado = (string) $request->input('estado', '');
        $medioPagoId = $request->filled('medio_pago_id') ? $request->integer('medio_pago_id') : null;

        $filtradas = $this->filtrarVentas(Venta::query(), $request, $desde, $hasta);

        $ventas = (clone $filtradas)
            ->with(['cliente', 'vendedor', 'pagos.medioPago'])
            ->withExists(['comprobantes as facturada' => fn ($q) => $q->whereIn('estado', ['autorizado', 'pendiente'])])
            ->orderByDesc('fecha')
            ->paginate(25)
            ->withQueryString();

        $statsQuery = (clone $filtradas);
        if ($estado !== 'anulada') {
            $statsQuery->where('ventas.estado', 'completada');
        }
        $stats = $statsQuery->toBase()
            ->selectRaw('COUNT(*) as cantidad, COALESCE(SUM(ventas.total), 0) as total')
            ->first();

        $porMedio = $this->acumuladoPorMedio($request, $desde, $hasta, $estado, $medioPagoId);

        $emisores = auth()->user()->can('facturacion.emitir')
            ? \App\Models\Emisor::with(['puntosVenta' => fn ($q) => $q->where('activo', true)])
                ->where('activo', true)
                ->orderBy('razon_social')
                ->get()
            : collect();

        return view('ventas.index', [
            'ventas' => $ventas,
            'totalPeriodo' => (float) ($stats->total ?? 0),
            'cantidadPeriodo' => (int) ($stats->cantidad ?? 0),
            'porMedio' => $porMedio,
            'mediosPago' => MedioPago::query()
                ->where('activo', true)
                ->orderByRaw("CASE WHEN tipo = 'efectivo' THEN 0 ELSE 1 END")
                ->orderBy('nombre')
                ->get(['id', 'nombre', 'tipo']),
            'desde' => $desde,
            'hasta' => $hasta,
            'emisores' => $emisores,
        ]);
    }

    private function filtrarVentas(Builder $query, Request $request, $desde, $hasta): Builder
    {
        $query->whereBetween('ventas.fecha', [$desde, $hasta]);

        $estado = (string) $request->input('estado', '');
        if ($estado === 'facturada') {
            $query->where('ventas.estado', 'completada')
                ->whereExists(fn ($q) => $this->existeComprobanteFiscal($q));
        } elseif ($estado === 'completada') {
            $query->where('ventas.estado', 'completada')
                ->whereNotExists(fn ($q) => $this->existeComprobanteFiscal($q));
        } elseif ($estado === 'anulada') {
            $query->where('ventas.estado', 'anulada');
        }

        if ($request->filled('medio_pago_id')) {
            $medioId = $request->integer('medio_pago_id');
            $query->whereExists(function ($q) use ($medioId) {
                $q->selectRaw('1')
                    ->from('venta_pagos')
                    ->whereColumn('venta_pagos.venta_id', 'ventas.id')
                    ->where('venta_pagos.medio_pago_id', $medioId);
            });
        }

        return $query;
    }

    private function existeComprobanteFiscal($q): void
    {
        $q->selectRaw('1')
            ->from('comprobantes')
            ->whereColumn('comprobantes.venta_id', 'ventas.id')
            ->whereIn('comprobantes.estado', ['autorizado', 'pendiente']);
    }

    private function acumuladoPorMedio(Request $request, $desde, $hasta, string $estado, ?int $medioPagoId)
    {
        $query = DB::table('venta_pagos')
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('medios_pago', 'medios_pago.id', '=', 'venta_pagos.medio_pago_id')
            ->where('ventas.empresa_id', auth()->user()->empresa_id)
            ->whereBetween('ventas.fecha', [$desde, $hasta]);

        if ($estado === 'anulada') {
            $query->where('ventas.estado', 'anulada');
        } else {
            $query->where('ventas.estado', 'completada');
        }

        if ($estado === 'facturada') {
            $query->whereExists(fn ($q) => $this->existeComprobanteFiscal($q));
        } elseif ($estado === 'completada') {
            $query->whereNotExists(fn ($q) => $this->existeComprobanteFiscal($q));
        }

        if ($medioPagoId) {
            $query->where('venta_pagos.medio_pago_id', $medioPagoId);
        }

        return $query
            ->groupBy('medios_pago.nombre')
            ->orderByDesc('total')
            ->get([
                'medios_pago.nombre',
                DB::raw('SUM(venta_pagos.importe) as total'),
            ]);
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
