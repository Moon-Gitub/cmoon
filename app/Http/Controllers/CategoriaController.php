<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empresa;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CategoriaController extends Controller
{
    public function index(): View
    {
        return view('categorias.index', [
            'categorias' => Categoria::withCount('productos')->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('categorias.gestionar'), 403);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('categorias', 'nombre')->where('empresa_id', auth()->user()->empresa_id),
            ],
        ]);

        Categoria::create([...$datos, 'empresa_id' => auth()->user()->empresa_id, 'activa' => true]);

        return back()->with('ok', 'Categoría creada.');
    }

    public function update(Request $request, Categoria $categoria): RedirectResponse
    {
        abort_unless(auth()->user()->can('categorias.gestionar'), 403);

        $datos = $request->validate([
            'nombre' => [
                'required', 'string', 'max:100',
                Rule::unique('categorias', 'nombre')
                    ->where('empresa_id', $categoria->empresa_id)
                    ->ignore($categoria),
            ],
            'activa' => ['boolean'],
        ]);

        $categoria->update([...$datos, 'activa' => $request->boolean('activa')]);

        return back()->with('ok', 'Categoría actualizada.');
    }

    public function destroy(Categoria $categoria): RedirectResponse
    {
        abort_unless(auth()->user()->can('categorias.gestionar'), 403);

        if ($categoria->productos()->exists()) {
            return back()->with('error', 'No se puede eliminar: tiene productos asociados.');
        }

        $categoria->delete();

        return back()->with('ok', 'Categoría eliminada.');
    }
}
