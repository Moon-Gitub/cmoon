<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Emisor;
use App\Models\PuntoVenta;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class EmisorController extends Controller
{
    public function index(): View
    {
        return view('emisores.index', [
            'emisores' => Emisor::with('puntosVenta')->orderBy('razon_social')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('emisores.gestionar'), 403);

        $datos = $this->validar($request);

        Emisor::create([...$datos, 'empresa_id' => auth()->user()->empresa_id]);

        return back()->with('ok', 'Emisor creado. Ahora subí el certificado AFIP y creá un punto de venta.');
    }

    public function update(Request $request, Emisor $emisor): RedirectResponse
    {
        abort_unless(auth()->user()->can('emisores.gestionar'), 403);

        $emisor->update($this->validar($request, $emisor));

        return back()->with('ok', 'Emisor actualizado.');
    }

    public function certificado(Request $request, Emisor $emisor): RedirectResponse
    {
        abort_unless(auth()->user()->can('emisores.gestionar'), 403);

        $request->validate([
            'certificado' => ['required', 'file', 'max:64'],
            'clave_privada' => ['required', 'file', 'max:64'],
        ], [], [
            'certificado' => 'certificado (.crt)',
            'clave_privada' => 'clave privada (.key)',
        ]);

        $certPath = $request->file('certificado')->storeAs('afip/certs', "emisor-{$emisor->id}.crt");
        $keyPath = $request->file('clave_privada')->storeAs('afip/certs', "emisor-{$emisor->id}.key");

        $emisor->update([
            'certificado_path' => $certPath,
            'clave_privada_path' => $keyPath,
        ]);

        return back()->with('ok', 'Certificado AFIP cargado.');
    }

    public function puntoVenta(Request $request, Emisor $emisor): RedirectResponse
    {
        abort_unless(auth()->user()->can('emisores.gestionar'), 403);

        $datos = $request->validate([
            'numero' => [
                'required', 'integer', 'min:1', 'max:99999',
                Rule::unique('puntos_venta', 'numero')->where('emisor_id', $emisor->id),
            ],
            'descripcion' => ['nullable', 'string', 'max:255'],
        ], [], ['numero' => 'número de punto de venta']);

        $emisor->puntosVenta()->create([...$datos, 'activo' => true]);

        return back()->with('ok', 'Punto de venta agregado.');
    }

    public function eliminarPuntoVenta(Emisor $emisor, PuntoVenta $puntoVenta): RedirectResponse
    {
        abort_unless(auth()->user()->can('emisores.gestionar'), 403);

        if ($puntoVenta->comprobantes()->exists()) {
            return back()->with('error', 'No se puede eliminar: tiene comprobantes emitidos.');
        }

        $puntoVenta->delete();

        return back()->with('ok', 'Punto de venta eliminado.');
    }

    private function validar(Request $request, ?Emisor $emisor = null): array
    {
        return $request->validate([
            'razon_social' => ['required', 'string', 'max:255'],
            'cuit' => [
                'required', 'string', 'max:13',
                Rule::unique('emisores', 'cuit')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($emisor),
            ],
            'condicion_iva' => ['required', 'in:RESPONSABLE_INSCRIPTO,MONOTRIBUTO'],
            'ingresos_brutos' => ['nullable', 'string', 'max:30'],
            'inicio_actividades' => ['nullable', 'date'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'entorno' => ['required', 'in:homologacion,produccion'],
            'activo' => ['boolean'],
        ], [], [
            'razon_social' => 'razón social',
            'condicion_iva' => 'condición frente al IVA',
        ]) + ['activo' => $request->boolean('activo')];
    }
}
