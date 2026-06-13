<?php

namespace App\Http\Controllers;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\Empresa;
use App\Models\MovimientoCuenta;
use App\Models\Producto;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class CompraController extends Controller
{
    public function index(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $compras = Compra::with(['proveedor', 'sucursal', 'usuario'])
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->orderByDesc('fecha')->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $totalPeriodo = Compra::where('estado', 'completada')
            ->whereBetween('fecha', [$desde->toDateString(), $hasta->toDateString()])
            ->sum('total');

        return view('compras.index', compact('compras', 'totalPeriodo', 'desde', 'hasta'));
    }

    public function create(): View
    {
        return view('compras.create', [
            'proveedores' => Proveedor::where('activo', true)->orderBy('razon_social')->get(['id', 'razon_social', 'cuit']),
            'sucursales' => Sucursal::where('activa', true)->get(['id', 'nombre']),
            'productos' => Producto::where('activo', true)->where('es_combo', false)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_compra']),
        ]);
    }

    public function store(Request $request, StockService $stockService): RedirectResponse
    {
        abort_unless(auth()->user()->can('compras.gestionar'), 403);

        $datos = $request->validate([
            'proveedor_id' => ['required', 'exists:proveedores,id'],
            'sucursal_id' => ['required', 'exists:sucursales,id'],
            'factura_numero' => ['nullable', 'string', 'max:30'],
            'condicion' => ['required', 'in:contado,cuenta_corriente'],
            'fecha' => ['required', 'date'],
            'observaciones' => ['nullable', 'string'],
            'actualizar_costos' => ['nullable', 'boolean'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.costo_unitario' => ['required', 'numeric', 'min:0'],
        ], [], ['proveedor_id' => 'proveedor', 'sucursal_id' => 'sucursal']);

        $compra = DB::transaction(function () use ($datos, $request, $stockService) {
            $total = 0.0;
            $items = [];

            foreach ($datos['items'] as $item) {
                $producto = isset($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $totalItem = round((float) $item['cantidad'] * (float) $item['costo_unitario'], 2);
                $total += $totalItem;
                $items[] = [...$item, 'producto' => $producto, 'total' => $totalItem];
            }

            $compra = Compra::create([
                'empresa_id' => auth()->user()->empresa_id,
                'sucursal_id' => $datos['sucursal_id'],
                'proveedor_id' => $datos['proveedor_id'],
                'user_id' => auth()->id(),
                'factura_numero' => $datos['factura_numero'] ?? null,
                'condicion' => $datos['condicion'],
                'total' => round($total, 2),
                'observaciones' => $datos['observaciones'] ?? null,
                'fecha' => $datos['fecha'],
            ]);

            foreach ($items as $item) {
                CompraItem::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $item['producto']?->id,
                    'descripcion' => $item['descripcion'] ?? $item['producto']?->nombre ?? 'Ítem',
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                    'total' => $item['total'],
                ]);

                if ($item['producto']) {
                    $stockService->mover(
                        $item['producto'],
                        (int) $datos['sucursal_id'],
                        (float) $item['cantidad'],
                        'compra',
                        "Compra #{$compra->id}".($compra->factura_numero ? " ({$compra->factura_numero})" : ''),
                        $compra,
                        auth()->id(),
                    );

                    if ($request->boolean('actualizar_costos')) {
                        $item['producto']->update(['precio_compra' => $item['costo_unitario']]);
                    }
                }
            }

            // Compra en cta. cte.: aumenta lo que le debemos al proveedor
            if ($datos['condicion'] === 'cuenta_corriente') {
                $proveedor = Proveedor::find($datos['proveedor_id']);
                MovimientoCuenta::create([
                    'titular_type' => $proveedor->getMorphClass(),
                    'titular_id' => $proveedor->id,
                    'tipo' => 'compra',
                    'concepto' => "Compra #{$compra->id}".($compra->factura_numero ? " ({$compra->factura_numero})" : ''),
                    'importe' => round($total, 2),
                    'referencia_type' => $compra->getMorphClass(),
                    'referencia_id' => $compra->id,
                    'user_id' => auth()->id(),
                    'fecha' => $datos['fecha'],
                ]);
            }

            return $compra;
        });

        return redirect()->route('compras.show', $compra)
            ->with('ok', "Compra #{$compra->id} registrada. Stock actualizado.");
    }

    public function show(Compra $compra): View
    {
        return view('compras.show', [
            'compra' => $compra->load(['items.producto', 'proveedor', 'sucursal', 'usuario']),
        ]);
    }

    public function anular(Compra $compra, StockService $stockService): RedirectResponse
    {
        abort_unless(auth()->user()->can('compras.gestionar'), 403);

        if ($compra->estado === 'anulada') {
            return back()->with('error', 'La compra ya está anulada.');
        }

        DB::transaction(function () use ($compra, $stockService) {
            foreach ($compra->items as $item) {
                if ($item->producto_id && $item->producto) {
                    $stockService->mover(
                        $item->producto,
                        $compra->sucursal_id,
                        -(float) $item->cantidad,
                        'anulacion',
                        "Anulación compra #{$compra->id}",
                        $compra,
                        auth()->id(),
                    );
                }
            }

            if ($compra->condicion === 'cuenta_corriente') {
                MovimientoCuenta::create([
                    'titular_type' => $compra->proveedor->getMorphClass(),
                    'titular_id' => $compra->proveedor_id,
                    'tipo' => 'ajuste',
                    'concepto' => "Anulación compra #{$compra->id}",
                    'importe' => -(float) $compra->total,
                    'referencia_type' => $compra->getMorphClass(),
                    'referencia_id' => $compra->id,
                    'user_id' => auth()->id(),
                    'fecha' => now()->toDateString(),
                ]);
            }

            $compra->update(['estado' => 'anulada']);
        });

        return back()->with('ok', "Compra #{$compra->id} anulada. Stock revertido.");
    }
}
