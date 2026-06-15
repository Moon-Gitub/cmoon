<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\ListaPrecio;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClienteController extends Controller
{
    public function index(Request $request): View
    {
        $clientes = Cliente::with(['listaPrecio', 'vendedor'])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(fn ($q) => $q
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('documento', 'like', "%{$buscar}%")
                    ->orWhere('email', 'like', "%{$buscar}%"));
            })
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('clientes.index', compact('clientes'));
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('clientes.crear'), 403);

        return view('clientes.form', [
            'cliente' => new Cliente(['tipo_documento' => 'DNI', 'condicion_iva' => 'CONSUMIDOR_FINAL']),
            'listas' => ListaPrecio::where('activa', true)->orderBy('nombre')->get(),
            'vendedores' => $this->vendedores(),
        ]);
    }

    public function show(Cliente $cliente): View
    {
        return view('clientes.show', [
            'cliente' => $cliente->load(['listaPrecio', 'vendedor']),
            'saldo' => $cliente->saldoCuenta(),
            'ventas' => $cliente->ventas()->where('estado', 'completada')->orderByDesc('fecha')->limit(10)->get(),
            'presupuestos' => $cliente->presupuestos()->orderByDesc('fecha')->limit(10)->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('clientes.crear'), 403);

        $cliente = Cliente::create([
            ...$this->validar($request),
            'empresa_id' => auth()->user()->empresa_id,
        ]);

        return redirect()->route('clientes.index')->with('ok', "Cliente {$cliente->nombre} creado.");
    }

    public function edit(Cliente $cliente): View
    {
        abort_unless(auth()->user()->can('clientes.editar'), 403);

        return view('clientes.form', [
            'cliente' => $cliente,
            'listas' => ListaPrecio::where('activa', true)->orderBy('nombre')->get(),
            'vendedores' => $this->vendedores(),
        ]);
    }

    public function update(Request $request, Cliente $cliente): RedirectResponse
    {
        abort_unless(auth()->user()->can('clientes.editar'), 403);

        $cliente->update($this->validar($request, $cliente));

        return redirect()->route('clientes.index')->with('ok', "Cliente {$cliente->nombre} actualizado.");
    }

    public function destroy(Cliente $cliente): RedirectResponse
    {
        abort_unless(auth()->user()->can('clientes.eliminar'), 403);

        if (round($cliente->saldoCuenta(), 2) != 0) {
            return back()->with('error', 'No se puede eliminar: tiene saldo en cuenta corriente.');
        }

        $cliente->delete();

        return redirect()->route('clientes.index')->with('ok', "Cliente {$cliente->nombre} eliminado.");
    }

    private function validar(Request $request, ?Cliente $cliente = null): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'tipo_documento' => ['required', 'in:DNI,CUIT,CUIL,OTRO'],
            'documento' => ['nullable', 'string', 'max:20'],
            'condicion_iva' => ['required', 'in:CONSUMIDOR_FINAL,RESPONSABLE_INSCRIPTO,MONOTRIBUTO,EXENTO'],
            'email' => ['nullable', 'email', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'localidad' => ['nullable', 'string', 'max:255'],
            'lista_precio_id' => ['nullable', Rule::exists('listas_precio', 'id')],
            'vendedor_id' => ['nullable', Rule::exists('users', 'id')],
            'limite_credito' => ['nullable', 'numeric', 'min:0'],
            'observaciones' => ['nullable', 'string'],
            'activo' => ['boolean'],
        ], [], [
            'tipo_documento' => 'tipo de documento',
            'condicion_iva' => 'condición frente al IVA',
            'lista_precio_id' => 'lista de precios',
            'limite_credito' => 'límite de crédito',
        ]) + ['activo' => $request->boolean('activo')];
    }

    private function vendedores()
    {
        return User::where('empresa_id', auth()->user()->empresa_id)
            ->where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
