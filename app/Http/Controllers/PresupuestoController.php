<?php

namespace App\Http\Controllers;

use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\Presupuesto;
use App\Models\PresupuestoItem;
use App\Models\Producto;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class PresupuestoController extends Controller
{
    public function index(Request $request): View
    {
        $presupuestos = Presupuesto::with(['cliente', 'usuario'])
            ->when($request->input('estado'), fn ($q, $estado) => $q->where('estado', $estado))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        return view('presupuestos.index', compact('presupuestos'));
    }

    public function create(): View
    {
        return view('presupuestos.create', [
            'clientes' => Cliente::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
            'productos' => Producto::where('activo', true)->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_venta']),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('presupuestos.gestionar'), 403);

        $datos = $request->validate([
            'cliente_id' => ['nullable', 'exists:clientes,id'],
            'valido_hasta' => ['nullable', 'date'],
            'observaciones' => ['nullable', 'string'],
            'items' => ['required', 'array', 'min:1'],
            'items.*.producto_id' => ['nullable', 'exists:productos,id'],
            'items.*.descripcion' => ['nullable', 'string', 'max:255'],
            'items.*.cantidad' => ['required', 'numeric', 'gt:0'],
            'items.*.precio_unitario' => ['required', 'numeric', 'min:0'],
        ]);

        $presupuesto = DB::transaction(function () use ($datos) {
            $empresaId = auth()->user()->empresa_id;
            $numero = (int) Presupuesto::where('empresa_id', $empresaId)->lockForUpdate()->max('numero') + 1;

            $total = 0.0;
            $items = [];
            foreach ($datos['items'] as $item) {
                $producto = isset($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $totalItem = round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2);
                $total += $totalItem;
                $items[] = [...$item, 'producto' => $producto, 'total' => $totalItem];
            }

            $presupuesto = Presupuesto::create([
                'empresa_id' => $empresaId,
                'cliente_id' => $datos['cliente_id'] ?? null,
                'user_id' => auth()->id(),
                'numero' => $numero,
                'total' => round($total, 2),
                'valido_hasta' => $datos['valido_hasta'] ?? null,
                'observaciones' => $datos['observaciones'] ?? null,
                'fecha' => now()->toDateString(),
            ]);

            foreach ($items as $item) {
                PresupuestoItem::create([
                    'presupuesto_id' => $presupuesto->id,
                    'producto_id' => $item['producto']?->id,
                    'descripcion' => $item['descripcion'] ?? $item['producto']?->nombre ?? 'Ítem',
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'total' => $item['total'],
                ]);
            }

            return $presupuesto;
        });

        return redirect()->route('presupuestos.show', $presupuesto)
            ->with('ok', "Presupuesto #{$presupuesto->numero} creado.");
    }

    public function show(Presupuesto $presupuesto): View
    {
        return view('presupuestos.show', [
            'presupuesto' => $presupuesto->load(['items.producto', 'cliente', 'usuario', 'venta']),
            'empresa' => auth()->user()->empresa,
        ]);
    }

    public function anular(Presupuesto $presupuesto): RedirectResponse
    {
        abort_unless(auth()->user()->can('presupuestos.gestionar'), 403);

        if ($presupuesto->estado === 'convertido') {
            return back()->with('error', 'No se puede anular: ya fue convertido en venta.');
        }

        $presupuesto->update(['estado' => 'anulado']);

        return back()->with('ok', "Presupuesto #{$presupuesto->numero} anulado.");
    }
}
