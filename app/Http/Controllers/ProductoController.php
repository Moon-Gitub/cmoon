<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\Sucursal;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $productos = Producto::with(['categoria', 'stocks'])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                $buscar = $request->string('buscar');
                $query->where(fn ($q) => $q
                    ->where('nombre', 'like', "%{$buscar}%")
                    ->orWhere('codigo', 'like', "%{$buscar}%"));
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->input('estado') === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when($request->input('estado') !== 'inactivos', fn ($q) => $q->where('activo', true))
            ->orderBy('nombre')
            ->paginate(20)
            ->withQueryString();

        return view('productos.index', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('productos.crear'), 403);

        return view('productos.form', [
            'producto' => new Producto(['alicuota_iva' => 21, 'unidad' => 'UN']),
            'categorias' => Categoria::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.crear'), 403);

        $producto = Producto::create([
            ...$this->validar($request),
            'empresa_id' => Empresa::value('id'),
        ]);

        return redirect()->route('productos.index')
            ->with('ok', "Producto {$producto->nombre} creado.");
    }

    public function edit(Producto $producto): View
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        return view('productos.form', [
            'producto' => $producto,
            'categorias' => Categoria::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $producto->update($this->validar($request, $producto));

        return redirect()->route('productos.index')
            ->with('ok', "Producto {$producto->nombre} actualizado.");
    }

    public function destroy(Producto $producto): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.eliminar'), 403);

        $producto->delete();

        return redirect()->route('productos.index')
            ->with('ok', "Producto {$producto->nombre} eliminado.");
    }

    public function stock(Producto $producto): View
    {
        abort_unless(auth()->user()->can('stock.ajustar'), 403);

        return view('productos.stock', [
            'producto' => $producto->load('stocks'),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'movimientos' => $producto->movimientosStock()
                ->with(['sucursal', 'usuario'])
                ->latest()
                ->limit(30)
                ->get(),
        ]);
    }

    public function ajustarStock(Request $request, Producto $producto, StockService $stockService): RedirectResponse
    {
        abort_unless(auth()->user()->can('stock.ajustar'), 403);

        $datos = $request->validate([
            'sucursal_id' => ['required', Rule::exists('sucursales', 'id')],
            'cantidad' => ['required', 'numeric', 'min:0'],
            'observacion' => ['nullable', 'string', 'max:255'],
        ]);

        $stockService->ajustarA(
            $producto,
            (int) $datos['sucursal_id'],
            (float) $datos['cantidad'],
            $datos['observacion'] ?? null,
        );

        return back()->with('ok', 'Stock ajustado.');
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        return $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('productos', 'codigo')
                    ->where('empresa_id', Empresa::value('id'))
                    ->ignore($producto)
                    ->withoutTrashed(),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => ['nullable', Rule::exists('categorias', 'id')],
            'unidad' => ['required', 'in:UN,KG,LT,MT'],
            'pesable' => ['boolean'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'alicuota_iva' => ['required', 'in:0,10.5,21,27'],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'activo' => ['boolean'],
        ], [], [
            'codigo' => 'código',
            'categoria_id' => 'categoría',
            'precio_compra' => 'precio de compra',
            'precio_venta' => 'precio de venta',
            'alicuota_iva' => 'alícuota de IVA',
            'stock_minimo' => 'stock mínimo',
        ]) + [
            'pesable' => $request->boolean('pesable'),
            'activo' => $request->boolean('activo'),
            'stock_minimo' => $request->input('stock_minimo') ?: 0,
        ];
    }
}
