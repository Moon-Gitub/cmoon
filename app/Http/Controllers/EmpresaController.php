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
        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);
        CatalogoCategoriaController::asegurarToken($empresa);

        return view('empresa.edit', ['empresa' => $empresa->fresh()]);
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
            'cotizacion_dolar' => ['nullable', 'numeric', 'min:0'],
            'logo' => ['nullable', 'image', 'max:2048'],
            'catalogo_fondo' => ['nullable', 'image', 'max:5120'],
            'catalogo_logo' => ['nullable', 'image', 'max:2048'],
            'catalogo_color_titulo' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'catalogo_color_texto' => ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'],
            'agente_retencion_iibb' => ['nullable', 'boolean'],
            'codigo_jurisdiccion_iibb' => ['nullable', 'integer', 'min:1'],
            'tipo_regimen_retencion_default' => ['nullable', 'integer', 'min:1'],
            'proximo_numero_recibo' => ['nullable', 'integer', 'min:1'],
        ], [], [
            'razon_social' => 'razón social',
            'nombre_fantasia' => 'nombre de fantasía',
            'condicion_iva' => 'condición frente al IVA',
            'inicio_actividades' => 'inicio de actividades',
            'catalogo_fondo' => 'fondo del catálogo PDF',
            'catalogo_logo' => 'logo del catálogo PDF',
        ]);

        unset($datos['logo'], $datos['catalogo_fondo'], $datos['catalogo_logo']);

        $datos['agente_retencion_iibb'] = $request->boolean('agente_retencion_iibb');
        $datos['cotizacion_dolar'] = (float) ($datos['cotizacion_dolar'] ?? 0);
        $datos['catalogo_color_titulo'] = $datos['catalogo_color_titulo'] ?? $empresa->catalogo_color_titulo ?? '#909e23';
        $datos['catalogo_color_texto'] = $datos['catalogo_color_texto'] ?? $empresa->catalogo_color_texto ?? '#f1f0ec';

        if ($request->hasFile('logo')) {
            $datos['logo_path'] = $request->file('logo')->store('logos', 'public');
        }
        if ($request->hasFile('catalogo_fondo')) {
            $datos['catalogo_fondo_path'] = $request->file('catalogo_fondo')->store('catalogo', 'public');
        }
        if ($request->hasFile('catalogo_logo')) {
            $datos['catalogo_logo_path'] = $request->file('catalogo_logo')->store('catalogo', 'public');
        }

        $empresa->update($datos);
        \App\Http\Controllers\CatalogoCategoriaController::asegurarToken($empresa->fresh());

        return back()->with('ok', 'Datos de la empresa actualizados.');
    }
}
