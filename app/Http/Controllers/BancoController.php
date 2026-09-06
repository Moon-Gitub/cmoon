<?php

namespace App\Http\Controllers;

use App\Models\CuentaBancaria;
use App\Services\BancoService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BancoController extends Controller
{
    public function index(): View
    {
        $cuentas = CuentaBancaria::query()
            ->orderByDesc('activa')
            ->orderBy('nombre')
            ->get();

        return view('bancos.index', compact('cuentas'));
    }

    public function store(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'nombre' => ['required', 'string', 'max:100'],
            'banco' => ['nullable', 'string', 'max:100'],
            'cbu' => ['nullable', 'string', 'max:30'],
            'alias' => ['nullable', 'string', 'max:100'],
            'moneda' => ['nullable', 'string', 'max:10'],
        ]);

        $cuenta = CuentaBancaria::create([
            'empresa_id' => auth()->user()->empresa_id,
            'nombre' => $datos['nombre'],
            'banco' => $datos['banco'] ?? null,
            'cbu' => $datos['cbu'] ?? null,
            'alias' => $datos['alias'] ?? null,
            'moneda' => $datos['moneda'] ?? 'ARS',
            'activa' => true,
        ]);

        return redirect()->route('bancos.show', $cuenta)
            ->with('ok', 'Cuenta bancaria creada.');
    }

    public function show(CuentaBancaria $cuenta): View
    {
        $movimientos = $cuenta->movimientos()
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(40);

        return view('bancos.show', [
            'cuenta' => $cuenta,
            'movimientos' => $movimientos,
        ]);
    }

    public function importCsv(Request $request, CuentaBancaria $cuenta, BancoService $banco): RedirectResponse
    {
        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $creados = $banco->importarCsv($cuenta, $request->file('archivo'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', count($creados).' movimientos importados.');
    }
}
