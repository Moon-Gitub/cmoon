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
        $presupuestos = Presupuesto::with('cliente')
            ->whereIn('estado', ['aprobado', 'pendiente', 'pendiente_aprobacion'])
            ->whereDoesntHave('remitos')
            ->orderByDesc('fecha')
            ->limit(100)
            ->get();

        return view('remitos.create', compact('presupuestos'));
    }

    public function store(Request $request, RemitoService $servicio): RedirectResponse
    {
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
            'remito' => $remito->load(['items.producto', 'cliente', 'sucursal', 'presupuesto', 'usuario']),
        ]);
    }

    public function entregar(Remito $remito, RemitoService $servicio): RedirectResponse
    {
        try {
            $servicio->marcarEntregado($remito);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Remito marcado como entregado.');
    }
}
