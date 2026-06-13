<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Sucursal;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SucursalController extends Controller
{
    public function index(): View
    {
        return view('sucursales.index', [
            'sucursales' => Sucursal::withCount('usuarios')->orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('sucursales.gestionar'), 403);

        return view('sucursales.form', ['sucursal' => new Sucursal]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('sucursales.gestionar'), 403);

        $datos = $this->validar($request);

        Sucursal::create([...$datos, 'empresa_id' => auth()->user()->empresa_id]);

        return redirect()->route('sucursales.index')->with('ok', 'Sucursal creada.');
    }

    public function edit(Sucursal $sucursal): View
    {
        abort_unless(auth()->user()->can('sucursales.gestionar'), 403);

        return view('sucursales.form', compact('sucursal'));
    }

    public function update(Request $request, Sucursal $sucursal): RedirectResponse
    {
        abort_unless(auth()->user()->can('sucursales.gestionar'), 403);

        $sucursal->update($this->validar($request, $sucursal));

        return redirect()->route('sucursales.index')->with('ok', 'Sucursal actualizada.');
    }

    public function destroy(Sucursal $sucursal): RedirectResponse
    {
        abort_unless(auth()->user()->can('sucursales.gestionar'), 403);

        if ($sucursal->usuarios()->exists()) {
            return back()->with('error', 'No se puede eliminar: tiene usuarios asignados.');
        }

        $sucursal->delete();

        return redirect()->route('sucursales.index')->with('ok', 'Sucursal eliminada.');
    }

    private function validar(Request $request, ?Sucursal $sucursal = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('sucursales', 'nombre')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($sucursal),
            ],
            'codigo' => ['nullable', 'string', 'max:10'],
            'domicilio' => ['nullable', 'string', 'max:255'],
            'telefono' => ['nullable', 'string', 'max:30'],
            'activa' => ['boolean'],
        ]) + ['activa' => $request->boolean('activa')];
    }
}
