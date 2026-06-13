<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\MedioPago;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class MedioPagoController extends Controller
{
    public function index(): View
    {
        return view('medios-pago.index', [
            'medios' => MedioPago::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('medios-pago.gestionar'), 403);

        MedioPago::create([
            ...$this->validar($request),
            'empresa_id' => auth()->user()->empresa_id,
        ]);

        return back()->with('ok', 'Medio de pago creado.');
    }

    public function update(Request $request, MedioPago $medioPago): RedirectResponse
    {
        abort_unless(auth()->user()->can('medios-pago.gestionar'), 403);

        $medioPago->update($this->validar($request, $medioPago));

        return back()->with('ok', 'Medio de pago actualizado.');
    }

    public function destroy(MedioPago $medioPago): RedirectResponse
    {
        abort_unless(auth()->user()->can('medios-pago.gestionar'), 403);

        $medioPago->delete();

        return back()->with('ok', 'Medio de pago eliminado.');
    }

    private function validar(Request $request, ?MedioPago $medio = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('medios_pago', 'nombre')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($medio),
            ],
            'tipo' => ['required', 'in:efectivo,tarjeta_debito,tarjeta_credito,transferencia,qr,cheque,cuenta_corriente,otro'],
            'recargo_porcentaje' => ['nullable', 'numeric', 'between:-99,200'],
            'activo' => ['boolean'],
        ]) + [
            'activo' => $request->boolean('activo'),
            'recargo_porcentaje' => $request->input('recargo_porcentaje') ?: 0,
        ];
    }
}
