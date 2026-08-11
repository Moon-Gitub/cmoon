<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Producto;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ProductoApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $empresaId = $request->user()->empresa_id;

        $query = Producto::withoutGlobalScopes()
            ->where('empresa_id', $empresaId)
            ->with('categoria:id,nombre');

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('nombre', 'like', $q)
                    ->orWhere('codigo', 'like', $q);
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $productos = $query->orderBy('nombre')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json($productos);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $producto = Producto::withoutGlobalScopes()
            ->where('empresa_id', $request->user()->empresa_id)
            ->with('categoria:id,nombre')
            ->findOrFail($id);

        return response()->json($producto);
    }
}
