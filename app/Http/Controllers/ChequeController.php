<?php

namespace App\Http\Controllers;

use App\Models\Cheque;
use App\Models\Cliente;
use App\Models\Proveedor;
use App\Services\ChequeService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChequeController extends Controller
{
    public function index(Request $request): View
    {
        $query = Cheque::with(['cliente', 'proveedor'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->when($request->filled('tipo'), fn ($q) => $q->where('tipo', $request->input('tipo')))
            ->orderByDesc('fecha_vencimiento')
            ->orderByDesc('id');

        return view('cheques.index', [
            'cheques' => $query->paginate(25)->withQueryString(),
            'estado' => $request->input('estado'),
            'tipo' => $request->input('tipo'),
        ]);
    }

    public function create(): View
    {
        return view('cheques.create', [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->limit(500)->get(['id', 'nombre']),
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->limit(500)->get(['id', 'razon_social']),
        ]);
    }

    public function store(Request $request, ChequeService $cheques): RedirectResponse
    {
        $datos = $request->validate([
            'numero' => ['required', 'string', 'max:50'],
            'banco' => ['nullable', 'string', 'max:100'],
            'plaza' => ['nullable', 'string', 'max:100'],
            'fecha_emision' => ['required', 'date'],
            'fecha_vencimiento' => ['required', 'date', 'after_or_equal:fecha_emision'],
            'importe' => ['required', 'numeric', 'gt:0'],
            'tipo' => ['required', 'in:recibido,emitido'],
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'proveedor_id' => ['nullable', 'exists:proveedores,id'],
            'observaciones' => ['nullable', 'string'],
        ]);

        $cheque = $cheques->crear($datos);

        return redirect()->route('cheques.index')
            ->with('ok', "Cheque N° {$cheque->numero} registrado.");
    }

    public function cambiarEstado(Request $request, Cheque $cheque, ChequeService $cheques): RedirectResponse
    {
        $datos = $request->validate([
            'estado' => ['required', 'in:en_cartera,depositado,cobrado,rechazado,anulado'],
        ]);

        try {
            $cheques->cambiarEstado($cheque, $datos['estado']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('ok', 'Estado del cheque actualizado.');
    }
}
