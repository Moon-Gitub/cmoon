<?php

namespace App\Http\Controllers;

use App\Models\Deposito;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DepositoController extends Controller
{
    public function index(): View
    {
        $depositos = Deposito::with('sucursal')
            ->orderByDesc('activo')
            ->orderBy('nombre')
            ->get();

        return view('depositos.index', [
            'depositos' => $depositos,
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('depositos.gestionar'), 403);

        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'sucursal_id' => ['nullable', 'exists:sucursales,id'],
        ]);

        Deposito::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nombre' => $datos['nombre'],
            'sucursal_id' => $datos['sucursal_id'] ?? null,
            'activo' => true,
        ]);

        return back()->with('ok', 'Depósito creado.');
    }

    public function update(Request $request, Deposito $deposito): RedirectResponse
    {
        abort_unless(auth()->user()->can('depositos.gestionar'), 403);

        $datos = $request->validate([
            'nombre' => ['sometimes', 'required', 'string', 'max:100'],
            'sucursal_id' => ['nullable', 'exists:sucursales,id'],
            'activo' => ['sometimes', 'boolean'],
        ]);

        if ($request->has('activo') && ! $request->has('nombre')) {
            $deposito->update(['activo' => $request->boolean('activo')]);

            return back()->with('ok', $deposito->activo ? 'Depósito activado.' : 'Depósito desactivado.');
        }

        $deposito->update([
            'nombre' => $datos['nombre'] ?? $deposito->nombre,
            'sucursal_id' => array_key_exists('sucursal_id', $datos) ? $datos['sucursal_id'] : $deposito->sucursal_id,
            'activo' => $request->has('activo') ? $request->boolean('activo') : $deposito->activo,
        ]);

        return back()->with('ok', 'Depósito actualizado.');
    }
}
