<?php

namespace App\Http\Controllers;

use App\Models\Caja;
use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CajaController extends Controller
{
    public function index(): View
    {
        return view('cajas.index', [
            'cajas' => Caja::with(['sucursal', 'sesionAbierta.usuario'])->orderBy('nombre')->get(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'sesionesPrevias' => CajaSesion::with(['caja', 'usuario'])
                ->where('estado', 'cerrada')
                ->latest('cerrada_at')
                ->limit(10)
                ->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('cajas.gestionar'), 403);

        $datos = $request->validate([
            'sucursal_id' => ['required', Rule::exists('sucursales', 'id')],
            'nombre' => ['required', 'string', 'max:100',
                Rule::unique('cajas', 'nombre')->where('sucursal_id', $request->input('sucursal_id'))],
        ]);

        Caja::create([...$datos, 'activa' => true]);

        return back()->with('ok', 'Caja creada.');
    }

    public function abrir(Request $request, Caja $caja): RedirectResponse
    {
        abort_unless(auth()->user()->can('cajas.operar'), 403);

        if ($caja->sesionAbierta) {
            return back()->with('error', 'Esta caja ya tiene una sesión abierta.');
        }

        $datos = $request->validate([
            'monto_apertura' => ['required', 'numeric', 'min:0'],
        ], [], ['monto_apertura' => 'monto de apertura']);

        CajaSesion::create([
            'caja_id' => $caja->id,
            'user_id' => auth()->id(),
            'monto_apertura' => $datos['monto_apertura'],
            'estado' => 'abierta',
            'abierta_at' => now(),
        ]);

        return back()->with('ok', "Caja {$caja->nombre} abierta.");
    }

    public function cerrar(Request $request, CajaSesion $sesion): RedirectResponse
    {
        abort_unless(auth()->user()->can('cajas.operar'), 403);

        if ($sesion->estado !== 'abierta') {
            return back()->with('error', 'La sesión ya está cerrada.');
        }

        $datos = $request->validate([
            'monto_cierre_declarado' => ['required', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
        ], [], ['monto_cierre_declarado' => 'monto contado']);

        $sesion->update([
            'monto_cierre_declarado' => $datos['monto_cierre_declarado'],
            'monto_cierre_sistema' => $sesion->efectivoEsperado(),
            'observaciones' => $datos['observaciones'] ?? null,
            'estado' => 'cerrada',
            'cerrada_at' => now(),
        ]);

        return redirect()->route('cajas.sesion', $sesion)->with('ok', 'Caja cerrada.');
    }

    public function movimiento(Request $request, CajaSesion $sesion): RedirectResponse
    {
        abort_unless(auth()->user()->can('cajas.operar'), 403);

        if ($sesion->estado !== 'abierta') {
            return back()->with('error', 'La sesión está cerrada.');
        }

        $datos = $request->validate([
            'tipo' => ['required', 'in:ingreso,egreso'],
            'concepto' => ['required', 'string', 'max:255'],
            'importe' => ['required', 'numeric', 'gt:0'],
        ]);

        CajaMovimiento::create([
            ...$datos,
            'caja_sesion_id' => $sesion->id,
            'user_id' => auth()->id(),
        ]);

        return back()->with('ok', ucfirst($datos['tipo']).' registrado.');
    }

    public function sesion(CajaSesion $sesion): View
    {
        return view('cajas.sesion', [
            'sesion' => $sesion->load(['caja.sucursal', 'usuario', 'movimientos.usuario']),
            'ventas' => $sesion->ventas()->with('pagos.medioPago')->orderByDesc('fecha')->get(),
            'efectivoEsperado' => $sesion->efectivoEsperado(),
        ]);
    }
}
