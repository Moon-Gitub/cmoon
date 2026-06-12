<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\ListaPrecio;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ListaPrecioController extends Controller
{
    public function index(): View
    {
        return view('listas-precio.index', [
            'listas' => ListaPrecio::orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('listas-precio.gestionar'), 403);

        $datos = $this->validar($request);

        ListaPrecio::create([...$datos, 'empresa_id' => Empresa::value('id')]);

        return back()->with('ok', 'Lista de precios creada.');
    }

    public function update(Request $request, ListaPrecio $listaPrecio): RedirectResponse
    {
        abort_unless(auth()->user()->can('listas-precio.gestionar'), 403);

        $listaPrecio->update($this->validar($request, $listaPrecio));

        return back()->with('ok', 'Lista de precios actualizada.');
    }

    public function destroy(ListaPrecio $listaPrecio): RedirectResponse
    {
        abort_unless(auth()->user()->can('listas-precio.gestionar'), 403);

        $listaPrecio->delete();

        return back()->with('ok', 'Lista de precios eliminada.');
    }

    private function validar(Request $request, ?ListaPrecio $lista = null): array
    {
        return $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('listas_precio', 'nombre')
                    ->where('empresa_id', Empresa::value('id'))
                    ->ignore($lista),
            ],
            'porcentaje' => ['required', 'numeric', 'between:-99,500'],
            'activa' => ['boolean'],
        ]) + ['activa' => $request->boolean('activa')];
    }
}
