<?php

namespace App\Http\Controllers;

use App\Models\OrdenCompra;
use App\Models\Sucursal;
use App\Services\OcrCompraIaService;
use App\Services\OrdenCompraService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class OrdenCompraController extends Controller
{
    public function index(Request $request): View
    {
        $query = OrdenCompra::with(['proveedor', 'sucursal', 'usuario'])
            ->when($request->filled('estado'), fn ($q) => $q->where('estado', $request->input('estado')))
            ->orderByDesc('fecha')
            ->orderByDesc('id');

        return view('ordenes-compra.index', [
            'ordenes' => $query->paginate(25)->withQueryString(),
            'estado' => $request->input('estado'),
        ]);
    }

    public function create(): View
    {
        return view('ordenes-compra.create', [
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function store(Request $request, OrdenCompraService $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'sucursal_id' => ['required', 'exists:sucursales,id'],
            'fecha' => ['required', 'date'],
            'observaciones' => ['nullable', 'string'],
            'estado' => ['nullable', 'in:borrador,enviada'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ], [], ['proveedor_id' => 'proveedor', 'sucursal_id' => 'sucursal']);

        $orden = $servicio->crear($datos);

        return redirect()->route('ordenes-compra.show', $orden)
            ->with('ok', "Orden de compra #{$orden->numero} creada.");
    }

    public function show(OrdenCompra $ordenCompra): View
    {
        return view('ordenes-compra.show', [
            'orden' => $ordenCompra->load(['items.producto', 'proveedor', 'sucursal', 'usuario']),
        ]);
    }

    public function recibir(Request $request, OrdenCompra $ordenCompra, OrdenCompraService $servicio): RedirectResponse
    {
        $datos = $request->validate([
            'items' => ['required', 'array', 'min:1'],
            'items.*.id' => ['required', 'integer'],
            'items.*.cantidad_recibir' => ['nullable', 'numeric', 'min:0'],
        ]);

        try {
            $orden = $servicio->recibirParcial($ordenCompra, $datos['items']);
        } catch (\InvalidArgumentException $e) {
            return back()->with('error', $e->getMessage());
        }

        return redirect()->route('ordenes-compra.show', $orden)
            ->with('ok', 'Recepción registrada. Stock actualizado.');
    }

    public function ocr(Request $request, OcrCompraIaService $ocr): JsonResponse
    {
        $datos = $request->validate([
            'texto' => ['required', 'string', 'max:50000'],
        ]);

        $resultado = $ocr->parsearTexto((int) auth()->user()->empresa_id, $datos['texto']);

        return response()->json($resultado);
    }
}
