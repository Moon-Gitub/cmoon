<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\ListaPrecio;
use App\Models\Ruta;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class RutaController extends Controller
{
    public function index(): View
    {
        abort_unless(auth()->user()->can('rutas.gestionar'), 403);

        return view('rutas.index', [
            'rutas' => Ruta::with(['vendedor', 'clientes'])
                ->orderBy('nombre')
                ->get(),
            'vendedores' => User::where('empresa_id', auth()->user()->empresa_id)
                ->where('activo', true)
                ->orderBy('name')
                ->get(['id', 'name']),
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('rutas.gestionar'), 403);

        $datos = $this->validar($request);

        $ruta = Ruta::create([
            'empresa_id' => auth()->user()->empresa_id,
            'user_id' => $datos['user_id'],
            'nombre' => $datos['nombre'],
            'dia_semana' => $datos['dia_semana'],
            'activa' => $datos['activa'],
        ]);

        $this->syncClientes($ruta, $datos['cliente_ids'] ?? []);

        return back()->with('ok', "Ruta {$ruta->nombre} creada.");
    }

    public function update(Request $request, Ruta $ruta): RedirectResponse
    {
        abort_unless(auth()->user()->can('rutas.gestionar'), 403);

        $datos = $this->validar($request);

        $ruta->update([
            'user_id' => $datos['user_id'],
            'nombre' => $datos['nombre'],
            'dia_semana' => $datos['dia_semana'],
            'activa' => $datos['activa'],
        ]);

        $this->syncClientes($ruta, $datos['cliente_ids'] ?? []);

        return back()->with('ok', "Ruta {$ruta->nombre} actualizada.");
    }

    public function destroy(Ruta $ruta): RedirectResponse
    {
        abort_unless(auth()->user()->can('rutas.gestionar'), 403);

        $ruta->delete();

        return back()->with('ok', 'Ruta eliminada.');
    }

    private function validar(Request $request): array
    {
        return $request->validate([
            'nombre' => ['required', 'string', 'max:255'],
            'user_id' => ['required', 'exists:users,id'],
            'dia_semana' => ['nullable', 'integer', 'between:0,6'],
            'activa' => ['boolean'],
            'cliente_ids' => ['nullable', 'array'],
            'cliente_ids.*' => ['integer', 'exists:clientes,id'],
        ]) + ['activa' => $request->boolean('activa', true)];
    }

    private function syncClientes(Ruta $ruta, array $clienteIds): void
    {
        $sync = [];
        foreach (array_values(array_unique($clienteIds)) as $orden => $clienteId) {
            $sync[$clienteId] = ['orden' => $orden + 1];
        }
        $ruta->clientes()->sync($sync);
    }
}
