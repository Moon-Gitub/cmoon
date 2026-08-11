<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Cliente;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ClienteApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Cliente::withoutGlobalScopes()
            ->where('empresa_id', $request->user()->empresa_id);

        if ($request->filled('q')) {
            $q = '%'.$request->string('q').'%';
            $query->where(function ($builder) use ($q) {
                $builder->where('nombre', 'like', $q)
                    ->orWhere('documento', 'like', $q)
                    ->orWhere('email', 'like', $q);
            });
        }

        if ($request->has('activo')) {
            $query->where('activo', $request->boolean('activo'));
        }

        $clientes = $query->orderBy('nombre')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json($clientes);
    }
}
