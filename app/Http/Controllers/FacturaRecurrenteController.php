<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Emisor;
use App\Models\FacturaRecurrente;
use App\Services\FacturaRecurrenteService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class FacturaRecurrenteController extends Controller
{
    public function index(): View
    {
        $facturas = FacturaRecurrente::with(['cliente', 'emisor'])
            ->orderByDesc('activa')
            ->orderBy('proximo_vencimiento')
            ->paginate(30);

        return view('facturas-recurrentes.index', [
            'facturas' => $facturas,
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->limit(500)->get(['id', 'nombre', 'email']),
            'emisores' => Emisor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'cuit']),
        ]);
    }

    public function store(Request $request, FacturaRecurrenteService $service): RedirectResponse
    {
        $datos = $request->validate([
            'cliente_id' => ['required', 'exists:clientes,id'],
            'emisor_id' => ['nullable', 'exists:emisores,id'],
            'dia_mes' => ['required', 'integer', 'min:1', 'max:31'],
            'monto' => ['required', 'numeric', 'min:0.01'],
            'concepto' => ['required', 'string', 'max:255'],
        ]);

        $service->crear($datos);

        return back()->with('ok', 'Factura recurrente creada.');
    }

    public function toggle(FacturaRecurrente $facturaRecurrente): RedirectResponse
    {
        $facturaRecurrente->update([
            'activa' => ! $facturaRecurrente->activa,
        ]);

        $estado = $facturaRecurrente->activa ? 'activada' : 'pausada';

        return back()->with('ok', "Factura recurrente {$estado}.");
    }

    public function destroy(FacturaRecurrente $facturaRecurrente): RedirectResponse
    {
        $facturaRecurrente->delete();

        return back()->with('ok', 'Factura recurrente eliminada.');
    }
}
