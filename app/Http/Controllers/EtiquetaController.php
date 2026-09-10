<?php

namespace App\Http\Controllers;

use App\Models\Empresa;
use App\Models\Producto;
use App\Services\EtiquetaGondolaPdfService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response as SymfonyResponse;

class EtiquetaController extends Controller
{
    public function index(Request $request): View
    {
        abort_unless(auth()->user()->can('productos.ver'), 403);

        $buscar = trim((string) $request->input('buscar', ''));
        $soloCombos = $request->boolean('solo_combos');

        $candidatos = Producto::query()
            ->where('activo', true)
            ->when($soloCombos, fn ($q) => $q->where('es_combo', true))
            ->when($buscar !== '', function ($q) use ($buscar) {
                app(\App\Services\BusquedaService::class)->aplicarTerminos(
                    $q,
                    $buscar,
                    ['nombre', 'codigo'],
                    preferExact: ['codigo']
                );
            })
            ->orderBy('nombre')
            ->limit(80)
            ->get(['id', 'codigo', 'nombre', 'precio_venta', 'precio_promocional', 'promo_desde', 'promo_hasta', 'es_combo']);

        $seleccionIds = collect(session('etiquetas_seleccion', []))->map(fn ($id) => (int) $id)->unique()->values();
        $seleccion = $seleccionIds->isEmpty()
            ? collect()
            : Producto::query()
                ->whereIn('id', $seleccionIds)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre', 'precio_venta', 'precio_promocional', 'promo_desde', 'promo_hasta', 'es_combo']);

        return view('productos.etiquetas', [
            'candidatos' => $candidatos,
            'seleccion' => $seleccion,
            'buscar' => $buscar,
            'soloCombos' => $soloCombos,
        ]);
    }

    public function agregar(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.ver'), 403);

        $datos = $request->validate([
            'producto_ids' => ['required', 'array', 'min:1'],
            'producto_ids.*' => [
                'integer',
                Rule::exists('productos', 'id')->where('empresa_id', auth()->user()->empresa_id),
            ],
        ]);

        $actual = collect(session('etiquetas_seleccion', []))->map(fn ($id) => (int) $id);
        $nuevos = collect($datos['producto_ids'])->map(fn ($id) => (int) $id);
        session(['etiquetas_seleccion' => $actual->merge($nuevos)->unique()->values()->all()]);

        return back()->with('ok', 'Productos agregados a la impresión.');
    }

    public function quitar(Request $request): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.ver'), 403);

        $id = (int) $request->input('producto_id');
        $actual = collect(session('etiquetas_seleccion', []))
            ->map(fn ($x) => (int) $x)
            ->reject(fn ($x) => $x === $id)
            ->values()
            ->all();
        session(['etiquetas_seleccion' => $actual]);

        return back();
    }

    public function limpiar(): \Illuminate\Http\RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.ver'), 403);
        session()->forget('etiquetas_seleccion');

        return back()->with('ok', 'Selección vaciada.');
    }

    public function pdf(Request $request, EtiquetaGondolaPdfService $servicio): SymfonyResponse
    {
        abort_unless(auth()->user()->can('productos.ver'), 403);

        $datos = $request->validate([
            'tipo' => ['required', 'in:normal,oferta,qr,barcode,combo'],
        ]);

        $ids = collect(session('etiquetas_seleccion', []))->map(fn ($id) => (int) $id)->unique()->values();
        abort_if($ids->isEmpty(), 422, 'No hay productos seleccionados.');

        $productos = Producto::query()
            ->with(['componentes.componente'])
            ->whereIn('id', $ids)
            ->orderBy('nombre')
            ->get();

        abort_if($productos->isEmpty(), 404);

        $empresa = Empresa::findOrFail(auth()->user()->empresa_id);
        $binario = $servicio->generar($empresa, $productos, $datos['tipo']);
        $nombre = 'etiquetas-'.$datos['tipo'].'-'.now()->format('Ymd-His').'.pdf';

        return response($binario, 200, [
            'Content-Type' => 'application/pdf',
            'Content-Disposition' => 'inline; filename="'.$nombre.'"',
            'Cache-Control' => 'private, max-age=30',
        ]);
    }

    /** Página pública al escanear QR de góndola (por tenant / dominio). */
    public function consultaPrecio(string $codigo): View|Response
    {
        $producto = Producto::query()
            ->where('codigo', $codigo)
            ->where('activo', true)
            ->first();

        if (! $producto) {
            return response()->view('productos.consulta-precio', [
                'producto' => null,
                'codigo' => $codigo,
                'empresa' => Empresa::query()->where('activa', true)->first(),
            ], 404);
        }

        $empresa = Empresa::find($producto->empresa_id);

        return view('productos.consulta-precio', [
            'producto' => $producto,
            'codigo' => $codigo,
            'empresa' => $empresa,
        ]);
    }
}
