<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Models\Venta;
use App\Services\VentaService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class VentaApiController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $query = Venta::withoutGlobalScopes()
            ->where('empresa_id', $request->user()->empresa_id)
            ->with(['cliente:id,nombre', 'user:id,name']);

        if ($request->filled('origen')) {
            $query->where('origen', $request->string('origen'));
        }

        if ($request->filled('estado')) {
            $query->where('estado', $request->string('estado'));
        }

        if ($request->filled('desde')) {
            $query->whereDate('fecha', '>=', $request->date('desde'));
        }

        if ($request->filled('hasta')) {
            $query->whereDate('fecha', '<=', $request->date('hasta'));
        }

        $ventas = $query->orderByDesc('fecha')
            ->paginate(min((int) $request->integer('per_page', 50), 100));

        return response()->json($ventas);
    }

    public function show(Request $request, int $id): JsonResponse
    {
        $venta = Venta::withoutGlobalScopes()
            ->where('empresa_id', $request->user()->empresa_id)
            ->with(['items', 'pagos.medioPago', 'cliente', 'user:id,name'])
            ->findOrFail($id);

        return response()->json($venta);
    }

    /**
     * Crea una venta usando el mismo servicio del POS.
     * Requiere permiso implícito del usuario autenticado.
     */
    public function store(Request $request, VentaService $ventas): JsonResponse
    {
        if (! $request->user()->can('pos.vender') && ! $request->user()->can('ventas.crear')) {
            abort(403, 'Sin permiso para crear ventas.');
        }

        $datos = $request->validate([
            'uuid' => ['nullable', 'uuid'],
            'sucursal_id' => ['required', 'integer'],
            'cliente_id' => ['nullable', 'integer'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['required', 'integer'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric'],
            'items.*.descripcion' => ['nullable', 'string'],
            'pagos' => ['required', 'array', 'min:1'],
            'pagos.*.medio_pago_id' => ['required', 'integer'],
            'pagos.*.importe' => ['required', 'numeric'],
            'descuento' => ['nullable', 'numeric'],
            'recargo' => ['nullable', 'numeric'],
            'origen' => ['nullable', 'string', 'max:20'],
        ]);

        $datos['uuid'] = $datos['uuid'] ?? (string) \Illuminate\Support\Str::uuid();
        $datos['origen'] = $datos['origen'] ?? 'api';

        $venta = $ventas->crear($datos, $request->user()->id);

        return response()->json($venta->load(['items', 'pagos']), 201);
    }
}
