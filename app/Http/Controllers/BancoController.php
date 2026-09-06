<?php

namespace App\Http\Controllers;

use App\Models\CuentaBancaria;
use App\Models\MovimientoBancario;
use App\Models\Venta;
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
        abort_unless(auth()->user()->can('bancos.gestionar'), 403);

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

    public function show(Request $request, CuentaBancaria $cuentaBancaria): View
    {
        $soloPendientes = $request->boolean('solo_pendientes');

        $movimientos = $cuentaBancaria->movimientos()
            ->when($soloPendientes, fn ($q) => $q->where('conciliado', false))
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->paginate(40)
            ->withQueryString();

        $ventasRecientes = Venta::query()
            ->where('tipo', '!=', 'devolucion')
            ->orderByDesc('fecha')
            ->orderByDesc('id')
            ->limit(40)
            ->get(['id', 'numero', 'fecha', 'total']);

        return view('bancos.show', [
            'cuenta' => $cuentaBancaria,
            'movimientos' => $movimientos,
            'soloPendientes' => $soloPendientes,
            'ventasRecientes' => $ventasRecientes,
        ]);
    }

    public function importCsv(Request $request, CuentaBancaria $cuentaBancaria, BancoService $banco): RedirectResponse
    {
        abort_unless(auth()->user()->can('bancos.gestionar'), 403);

        $request->validate([
            'archivo' => ['required', 'file', 'mimes:csv,txt', 'max:5120'],
        ]);

        try {
            $creados = $banco->importarCsv($cuentaBancaria, $request->file('archivo'));
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', count($creados).' movimientos importados.');
    }

    public function conciliar(
        Request $request,
        CuentaBancaria $cuentaBancaria,
        MovimientoBancario $movimiento,
        BancoService $banco,
    ): RedirectResponse {
        abort_unless(auth()->user()->can('bancos.gestionar'), 403);
        abort_unless($movimiento->cuenta_bancaria_id === $cuentaBancaria->id, 404);

        $datos = $request->validate([
            'venta_id' => ['nullable', 'exists:ventas,id'],
        ]);

        $ref = null;
        if (! empty($datos['venta_id'])) {
            $ref = Venta::find($datos['venta_id']);
        }

        $banco->conciliar($movimiento, $ref);

        return back()->with('ok', 'Movimiento conciliado.');
    }

    public function desconciliar(
        CuentaBancaria $cuentaBancaria,
        MovimientoBancario $movimiento,
        BancoService $banco,
    ): RedirectResponse {
        abort_unless(auth()->user()->can('bancos.gestionar'), 403);
        abort_unless($movimiento->cuenta_bancaria_id === $cuentaBancaria->id, 404);

        $banco->desconciliar($movimiento);

        return back()->with('ok', 'Conciliación revertida.');
    }
}
