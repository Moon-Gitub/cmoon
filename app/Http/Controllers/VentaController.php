<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class VentaController extends Controller
{
    public function index(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfDay();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        $ventas = Venta::with(['cliente', 'vendedor', 'pagos.medioPago'])
            ->whereBetween('fecha', [$desde, $hasta])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('fecha')
            ->paginate(25)
            ->withQueryString();

        $totalPeriodo = Venta::where('estado', 'completada')
            ->whereBetween('fecha', [$desde, $hasta])
            ->sum('total');

        return view('ventas.index', [
            'ventas' => $ventas,
            'totalPeriodo' => $totalPeriodo,
            'desde' => $desde,
            'hasta' => $hasta,
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

        return back()->with('ok', "Venta #{$venta->numero} anulada. Stock repuesto.");
    }

    public function ticket(Venta $venta): View
    {
        return view('ventas.ticket', [
            'venta' => $venta->load(['items', 'pagos.medioPago', 'cliente', 'vendedor', 'sucursal']),
            'empresa' => auth()->user()->empresa,
        ]);
    }
}
