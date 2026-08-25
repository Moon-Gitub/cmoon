<?php

namespace App\Http\Controllers;

use App\Services\BusquedaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class BusquedaController extends Controller
{
    public function __construct(private readonly BusquedaService $busqueda) {}

    public function productos(Request $request): JsonResponse
    {
        return response()->json($this->busqueda->productos(
            (string) $request->input('q', ''),
            $request->integer('limit', 20) ?: 20,
            $request->boolean('activos', true),
            $request->boolean('sin_combos', false),
        ));
    }

    public function clientes(Request $request): JsonResponse
    {
        return response()->json($this->busqueda->clientes(
            (string) $request->input('q', ''),
            $request->integer('limit', 20) ?: 20,
            $request->boolean('activos', true),
        ));
    }

    public function proveedores(Request $request): JsonResponse
    {
        return response()->json($this->busqueda->proveedores(
            (string) $request->input('q', ''),
            $request->integer('limit', 20) ?: 20,
            $request->boolean('activos', true),
        ));
    }

    public function ventas(Request $request): JsonResponse
    {
        return response()->json($this->busqueda->ventas(
            (string) $request->input('q', ''),
            $request->integer('limit', 20) ?: 20,
        ));
    }

    public function comprobantes(Request $request): JsonResponse
    {
        return response()->json($this->busqueda->comprobantes(
            (string) $request->input('q', ''),
            $request->integer('limit', 20) ?: 20,
        ));
    }

    public function usuarios(Request $request): JsonResponse
    {
        return response()->json($this->busqueda->usuarios(
            (string) $request->input('q', ''),
            $request->integer('limit', 20) ?: 20,
        ));
    }
}
