<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * ABM de empresas del sistema (multi-empresa). Cada empresa tiene sus
 * propios productos, clientes, ventas, emisores y usuarios.
 */
class EmpresasAdminController extends Controller
{
    public function index(): View
    {
        return view('empresas.index', [
            'empresas' => Empresa::withCount('usuarios')->orderBy('razon_social')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $this->validar($request);

        $empresa = Empresa::create([...$datos, 'activa' => true]);

        // Toda empresa arranca con una sucursal principal
        Sucursal::withoutGlobalScope('empresa')->create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Casa central',
            'activa' => true,
        ]);

        return back()->with('ok', "Empresa {$empresa->razon_social} creada con su sucursal principal. Ahora asignale usuarios.");
    }

    public function update(Request $request, Empresa $empresa): RedirectResponse
    {
        $empresa->update($this->validar($request, $empresa) + [
            'activa' => $request->boolean('activa'),
        ]);

        return back()->with('ok', 'Empresa actualizada.');
    }

    private function validar(Request $request, ?Empresa $empresa = null): array
    {
        return $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'string', 'max:13', Rule::unique('empresas', 'cuit')->ignore($empresa)],
            'condicion_iva' => ['required', 'in:RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
        ], [], [
            'razon_social' => 'razón social',
            'condicion_iva' => 'condición frente al IVA',
        ]);
    }
}
