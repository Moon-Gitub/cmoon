<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class EmpresaController extends Controller
{
    public function edit(): View
    {
        return view('empresa.edit', ['empresa' => Empresa::findOrFail(auth()->user()->empresa_id)]);
    }

    public function update(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('empresa.editar'), 403);

        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);

        $datos = $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'nombre_fantasia' => ['nullable', 'string', 'max:255'],
            'cuit' => ['nullable', 'string', 'max:13'],
            'condicion_iva' => ['required', 'in:RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'ingresos_brutos' => ['nullable', 'string', 'max:30'],
            'inicio_actividades' => ['nullable', 'date'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'provincia' => ['nullable', 'string', 'max:255'],
            'codigo_postal' => ['nullable', 'string', 'max:10'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'color_primario' => ['required', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'logo' => ['nullable', 'image', 'max:2048'],
        ], [], [
            'razon_social' => 'razón social',
            'nombre_fantasia' => 'nombre de fantasía',
            'condicion_iva' => 'condición frente al IVA',
            'inicio_actividades' => 'inicio de actividades',
        ]);

        unset($datos['logo']);

        if ($request->hasFile('logo')) {
            $datos['logo_path'] = $request->file('logo')->store('logos', 'public');
        }

        $empresa->update($datos);

        return back()->with('ok', 'Datos de la empresa actualizados.');
    }
}
