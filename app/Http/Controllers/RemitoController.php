<?php

namespace App\Http\Controllers;

use App\Models\Presupuesto;
use App\Models\Remito;
use App\Services\RemitoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class RemitoController extends Controller
{
    public function index(Request $request): View
    {
        $query = Remito::with(['cliente', 'sucursal', 'presupuesto'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return view('remitos.index', [
            'remitos' => $query->paginate(25)->withQueryString(),
            'estado' => $request->input('estado'),
        ]);
    }

    public function createFromPresupuesto(): View
    {
        abort_unless(auth()->user()->can('remitos.gestionar'), 403);

        $presupuestos = Presupuesto::with('cliente')
            ->whereIn('estado', ['pedido', 'aprobado', 'pendiente'])
            ->whereDoesntHave('remitos')
            ->orderByDesc('fecha')
            ->limit(100)
            ->get();

        return view('remitos.create', compact('presupuestos'));
    }

    public function store(Request $request, RemitoService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('remitos.gestionar'), 403);

        $datos = $request->validate([
            'presupuesto_id' => ['required', 'exists:presupuestos,id'],
        ]);

        $presupuesto = Presupuesto::findOrFail($datos['presupuesto_id']);

        try {
            $remito = $servicio->crearDesdePresupuesto($presupuesto);
        } catch (\InvalidArgumentException $e) {
            return back()->withInput()->with('error', $e->getMessage());
        }

        return redirect()->route('remitos.show', $remito)
            ->with('ok', "Remito #{$remito->numero} emitido.");
    }

    public function show(Remito $remito): View
    {
        return view('remitos.show', [
            'remito' => $remito->load(['items.producto', 'cliente', 'sucursal', 'presupuesto', 'usuario', 'venta']),
        ]);
    }

    public function entregar(Remito $remito, RemitoService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('remitos.gestionar'), 403);

        try {
            $servicio->marcarEntregado($remito);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Remito marcado como entregado.');
    }

    public function facturar(Remito $remito, RemitoService $servicio): RedirectResponse
    {
        abort_unless(auth()->user()->can('remitos.gestionar') || auth()->user()->can('pos.vender'), 403);

        try {
            $venta = $servicio->convertirAVenta($remito);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        } catch (\Illuminate\Validation\ValidationException $e) {
            return back()->with('error', collect($e->errors())->flatten()->first() ?? $e->getMessage());
        }

        if (auth()->user()->can('ventas.ver') && auth()->user()->can('facturacion.emitir')) {
            return redirect()->route('ventas.show', $venta)
                ->with('ok', "Venta #{$venta->numero} creada desde remito. Podés facturarla desde esta pantalla.");
        }

        if (auth()->user()->can('ventas.ver')) {
            return redirect()->route('ventas.show', $venta)
                ->with('ok', "Venta #{$venta->numero} creada desde remito.");
        }

        return redirect()->route('remitos.show', $remito)
            ->with('ok', "Venta #{$venta->numero} creada desde remito.");
    }
}
