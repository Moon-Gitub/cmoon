<?php

namespace App\Http\Controllers;

use App\Models\Categoria;
use App\Models\Deposito;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ProductoLote;
use App\Models\Sucursal;
use App\Services\StockService;
use App\Support\TableSort;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ProductoController extends Controller
{
    public function index(Request $request): View
    {
        $query = Producto::with(['categoria', 'stocks'])
            ->withSum('stocks as stock_total', 'cantidad')
            ->when($request->filled('buscar'), function ($query) use ($request) {
                app(\App\Services\BusquedaService::class)
                    ->aplicarTerminos($query, (string) $request->input('buscar'), ['nombre', 'codigo'], preferExact: ['codigo']);
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->input('estado') === 'inactivos', fn ($q) => $q->where('activo', false))
            ->when($request->input('estado') !== 'inactivos', fn ($q) => $q->where('activo', true))
            ->when($request->filled('canal'), fn ($q) => $q->publicarEn((string) $request->input('canal')));

        [$sort, $dir] = TableSort::apply($query, $request, [
            'codigo' => 'codigo',
            'nombre' => 'nombre',
            'categoria' => fn ($q, $d) => $q->orderBy(
                \App\Models\Categoria::select('nombre')->whereColumn('categorias.id', 'productos.categoria_id'),
                $d
            ),
            'precio_compra' => 'precio_compra',
            'precio_venta' => 'precio_venta',
            'iva' => 'alicuota_iva',
            'stock' => 'stock_total',
        ], 'nombre');

        $productos = $query->paginate(20)->withQueryString();

        return view('productos.index', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('nombre')->get(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function create(): View
    {
        abort_unless(auth()->user()->can('productos.crear'), 403);

        return view('productos.form', [
            'producto' => new Producto([
                'alicuota_iva' => 21,
                'unidad' => 'UN',
                'margen_ganancia' => 40,
            ]),
            'categorias' => Categoria::where('activa', true)->orderBy('nombre')->get(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cotizacionDolar' => (float) (auth()->user()->empresa?->cotizacion_dolar ?? 0),
        ]);
    }

    public function store(Request $request, StockService $stockService): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.crear'), 403);

        $datos = $this->validar($request);
        $stockInicial = $datos['stock_inicial'] ?? null;
        $sucursalStockId = $datos['sucursal_stock_id'] ?? null;
        unset($datos['stock_inicial'], $datos['sucursal_stock_id']);

        $producto = Producto::create([
            ...$datos,
            'empresa_id' => auth()->user()->empresa_id,
        ]);

        if ($stockInicial !== null && $stockInicial > 0) {
            $sucursalId = $sucursalStockId
                ?? Sucursal::where('activa', true)->orderBy('id')->value('id');

            if ($sucursalId) {
                $stockService->ajustarA(
                    $producto,
                    (int) $sucursalId,
                    (float) $stockInicial,
                    'Stock inicial al crear producto'
                );
            }
        }

        return redirect()->route('productos.index')
            ->with('ok', "Producto {$producto->nombre} creado.");
    }

    public function edit(Producto $producto): View
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        return view('productos.form', [
            'producto' => $producto,
            'categorias' => Categoria::where('activa', true)->orderBy('nombre')->get(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(['id', 'nombre']),
            'cotizacionDolar' => (float) (auth()->user()->empresa?->cotizacion_dolar ?? 0),
        ]);
    }

    public function update(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $datos = $this->validar($request, $producto);
        unset($datos['stock_inicial'], $datos['sucursal_stock_id']);
        $producto->update($datos);

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

    public function lotes(Producto $producto): View
    {
        return view('productos.lotes', [
            'producto' => $producto,
            'lotes' => $producto->lotes()->with('deposito')->orderBy('codigo_lote')->orderBy('serie')->get(),
            'depositos' => Deposito::where('activo', true)->orderBy('nombre')->get(['id', 'nombre']),
        ]);
    }

    public function storeLote(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.editar') || auth()->user()->can('stock.ajustar'), 403);

        $datos = $request->validate([
            'codigo_lote' => ['required', 'string', 'max:100'],
            'serie' => ['nullable', 'string', 'max:100'],
            'deposito_id' => ['nullable', 'exists:depositos,id'],
            'vencimiento' => ['nullable', 'date'],
            'cantidad' => ['required', 'numeric'],
        ]);

        $serie = trim((string) ($datos['serie'] ?? ''));
        $serie = $serie === '' ? null : $serie;

        $lote = ProductoLote::query()
            ->where('producto_id', $producto->id)
            ->where('codigo_lote', $datos['codigo_lote'])
            ->when(
                $serie === null,
                fn ($q) => $q->where(fn ($q2) => $q2->whereNull('serie')->orWhere('serie', '')),
                fn ($q) => $q->where('serie', $serie),
            )
            ->first() ?? new ProductoLote([
                'producto_id' => $producto->id,
                'codigo_lote' => $datos['codigo_lote'],
                'serie' => $serie,
            ]);

        $lote->empresa_id = $producto->empresa_id;
        $lote->serie = $serie;
        $lote->deposito_id = array_key_exists('deposito_id', $datos) ? $datos['deposito_id'] : $lote->deposito_id;
        if (array_key_exists('vencimiento', $datos)) {
            $lote->vencimiento = $datos['vencimiento'];
        }
        $lote->cantidad = $datos['cantidad'];
        $lote->save();

        return back()->with('ok', 'Lote actualizado.');
    }

    private function validar(Request $request, ?Producto $producto = null): array
    {
        $request->merge([
            'categoria_id' => $request->input('categoria_id') ?: null,
        ]);

        $datos = $request->validate([
            'codigo' => [
                'required', 'string', 'max:50',
                Rule::unique('productos', 'codigo')
                    ->where('empresa_id', auth()->user()->empresa_id)
                    ->ignore($producto)
                    ->withoutTrashed(),
            ],
            'nombre' => ['required', 'string', 'max:255'],
            'descripcion' => ['nullable', 'string'],
            'categoria_id' => [
                'nullable',
                Rule::exists('categorias', 'id')->where('empresa_id', auth()->user()->empresa_id),
            ],
            'unidad' => ['required', 'in:UN,KG,LT,MT'],
            'precio_compra' => ['required', 'numeric', 'min:0'],
            'precio_compra_dolar' => ['nullable', 'numeric', 'min:0'],
            'margen_ganancia' => ['nullable', 'numeric', 'min:0', 'max:9999'],
            'precio_venta' => ['required', 'numeric', 'min:0'],
            'precio_promocional' => ['nullable', 'numeric', 'min:0'],
            'promo_desde' => ['nullable', 'date'],
            'promo_hasta' => ['nullable', 'date', 'after_or_equal:promo_desde'],
            'alicuota_iva' => ['nullable', 'numeric', Rule::in([0, 10.5, 21, 27])],
            'stock_minimo' => ['nullable', 'numeric', 'min:0'],
            'stock_inicial' => ['nullable', 'numeric', 'min:0'],
            'sucursal_stock_id' => [
                'nullable',
                Rule::exists('sucursales', 'id')->where('empresa_id', auth()->user()->empresa_id),
            ],
        ], [], [
            'codigo' => 'código',
            'categoria_id' => 'categoría',
            'precio_compra' => 'precio de compra',
            'precio_compra_dolar' => 'precio de compra en dólares',
            'margen_ganancia' => 'margen de ganancia',
            'precio_venta' => 'precio de venta',
            'precio_promocional' => 'precio promocional',
            'promo_desde' => 'inicio de promo',
            'promo_hasta' => 'fin de promo',
            'alicuota_iva' => 'alícuota de IVA',
            'stock_minimo' => 'stock mínimo',
            'stock_inicial' => 'stock inicial',
            'sucursal_stock_id' => 'sucursal del stock',
        ]);

        return [
            ...$datos,
            'pesable' => $request->boolean('pesable'),
            'es_combo' => $request->boolean('es_combo'),
            'activo' => $request->boolean('activo'),
            'publicar_shopify' => $request->boolean('publicar_shopify'),
            'publicar_whatsapp' => $request->boolean('publicar_whatsapp'),
            'publicar_tiendanube' => $request->boolean('publicar_tiendanube'),
            'stock_minimo' => isset($datos['stock_minimo']) ? (float) $datos['stock_minimo'] : 0,
            'stock_inicial' => isset($datos['stock_inicial']) && $datos['stock_inicial'] !== ''
                ? (float) $datos['stock_inicial']
                : null,
            'sucursal_stock_id' => isset($datos['sucursal_stock_id']) ? (int) $datos['sucursal_stock_id'] : null,
            'precio_compra_dolar' => (float) ($datos['precio_compra_dolar'] ?? 0),
            'precio_promocional' => isset($datos['precio_promocional']) && $datos['precio_promocional'] !== ''
                ? (float) $datos['precio_promocional']
                : null,
            'promo_desde' => $datos['promo_desde'] ?? null,
            'promo_hasta' => $datos['promo_hasta'] ?? null,
            'margen_ganancia' => $request->boolean('utilizar_porcentaje')
                ? (float) ($datos['margen_ganancia'] ?? 0)
                : 0,
            'alicuota_iva' => array_key_exists('alicuota_iva', $datos) && $datos['alicuota_iva'] !== null
                ? (float) $datos['alicuota_iva']
                : 21.0,
        ];
    }

    /** Vacío o inválido → 21%. 0 / 10.5 / 27 solo si vienen explícitos. */
    private function alicuotaDesdeImport(mixed $raw): float
    {
        if ($raw === null || $raw === '') {
            return 21.0;
        }

        $valor = round((float) str_replace(',', '.', (string) $raw), 2);
        $permitidas = [0.0, 10.5, 21.0, 27.0];

        return in_array($valor, $permitidas, true) ? $valor : 21.0;
    }

    public function canales(Request $request): View
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $productos = Producto::with(['categoria', 'stocks'])
            ->when($request->filled('buscar'), function ($query) use ($request) {
                app(\App\Services\BusquedaService::class)->aplicarTerminos(
                    $query,
                    (string) $request->input('buscar'),
                    ['nombre', 'codigo'],
                    preferExact: ['codigo']
                );
            })
            ->when($request->filled('categoria'), fn ($q) => $q->where('categoria_id', $request->integer('categoria')))
            ->when($request->filled('canal'), fn ($q) => $q->publicarEn((string) $request->input('canal')))
            ->when($request->input('sin_canal') === '1', function ($q) {
                $q->where('publicar_shopify', false)
                    ->where('publicar_whatsapp', false)
                    ->where('publicar_tiendanube', false);
            })
            ->where('activo', true)
            ->orderBy('nombre')
            ->paginate(40)
            ->withQueryString();

        return view('productos.canales', [
            'productos' => $productos,
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function canalesAplicar(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $datos = $request->validate([
            'ids' => ['required', 'array', 'min:1'],
            'ids.*' => ['integer'],
            'accion' => ['required', 'in:activar,desactivar'],
            'canal' => ['required', 'in:shopify,whatsapp,tiendanube'],
        ]);

        $columna = 'publicar_'.$datos['canal'];
        $valor = $datos['accion'] === 'activar';

        $n = Producto::query()
            ->whereIn('id', $datos['ids'])
            ->update([$columna => $valor]);

        $label = Producto::CANALES[$datos['canal']];
        $verbo = $valor ? 'marcados para' : 'quitados de';

        return back()->with('ok', "{$n} producto(s) {$verbo} {$label}.");
    }

    public function combo(Producto $producto): View
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);
        abort_unless($producto->es_combo, 404);

        return view('productos.combo', [
            'producto' => $producto->load('componentes.componente'),
            'candidatos' => Producto::where('activo', true)
                ->where('es_combo', false)
                ->where('id', '!=', $producto->id)
                ->orderBy('nombre')
                ->get(['id', 'codigo', 'nombre']),
        ]);
    }

    public function agregarComponente(Request $request, Producto $producto): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $datos = $request->validate([
            'componente_id' => [
                'required',
                Rule::exists('productos', 'id'),
                Rule::notIn([$producto->id]),
                Rule::unique('combo_componentes', 'componente_id')->where('combo_id', $producto->id),
            ],
            'cantidad' => ['required', 'numeric', 'gt:0'],
        ], [], ['componente_id' => 'componente']);

        $producto->componentes()->create($datos);

        return back()->with('ok', 'Componente agregado al combo.');
    }

    public function quitarComponente(Producto $producto, \App\Models\ComboComponente $componente): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.editar'), 403);

        $componente->delete();

        return back()->with('ok', 'Componente quitado.');
    }

    public function importarForm(): View
    {
        abort_unless(auth()->user()->can('productos.crear'), 403);

        return view('productos.importar');
    }

    public function importar(Request $request, StockService $stockService): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.crear'), 403);

        $request->validate(['archivo' => ['required', 'file', 'mimes:csv,txt', 'max:10240']]);

        $contenido = file_get_contents($request->file('archivo')->getRealPath());
        // Normalizar encoding (Excel suele exportar en Latin-1)
        if (! mb_check_encoding($contenido, 'UTF-8')) {
            $contenido = mb_convert_encoding($contenido, 'UTF-8', 'ISO-8859-1');
        }

        $lineas = preg_split('/\r\n|\r|\n/', trim($contenido));
        $separador = str_contains($lineas[0], ';') ? ';' : ',';
        $cabecera = array_map(fn ($c) => strtolower(trim($c, "\xEF\xBB\xBF \"")), str_getcsv($lineas[0], $separador));

        $requeridas = ['codigo', 'nombre', 'precio_venta'];
        if (array_diff($requeridas, $cabecera) !== []) {
            return back()->with('error', 'El archivo tiene que tener al menos las columnas: codigo, nombre, precio_venta. Descargá la plantilla.');
        }

        $empresaId = auth()->user()->empresa_id;
        $sucursal = Sucursal::where('activa', true)->first();
        $creados = 0;
        $actualizados = 0;
        $errores = [];

        foreach (array_slice($lineas, 1) as $numero => $linea) {
            if (trim($linea) === '') {
                continue;
            }

            $fila = array_combine($cabecera, array_pad(str_getcsv($linea, $separador), count($cabecera), null));

            if (empty($fila['codigo']) || empty($fila['nombre']) || ! is_numeric(str_replace(',', '.', (string) $fila['precio_venta']))) {
                $errores[] = 'Línea '.($numero + 2).': datos incompletos o precio inválido.';

                continue;
            }

            $decimal = fn ($v) => $v !== null && $v !== '' ? (float) str_replace(',', '.', (string) $v) : null;

            $categoria = null;
            if (! empty($fila['categoria'])) {
                $categoria = Categoria::firstOrCreate(
                    ['empresa_id' => $empresaId, 'nombre' => trim($fila['categoria'])],
                    ['activa' => true],
                );
            }

            $producto = Producto::withTrashed()
                ->where('empresa_id', $empresaId)
                ->where('codigo', trim($fila['codigo']))
                ->first();

            $datos = [
                'nombre' => trim($fila['nombre']),
                'precio_venta' => $decimal($fila['precio_venta']),
                'precio_compra' => $decimal($fila['precio_compra'] ?? null) ?? 0,
                'alicuota_iva' => $this->alicuotaDesdeImport($fila['iva'] ?? null),
                'categoria_id' => $categoria?->id,
                'unidad' => in_array(strtoupper($fila['unidad'] ?? ''), ['UN', 'KG', 'LT', 'MT']) ? strtoupper($fila['unidad']) : 'UN',
                'stock_minimo' => $decimal($fila['stock_minimo'] ?? null) ?? 0,
                'activo' => true,
            ];

            if ($producto) {
                $producto->restore();
                $producto->update($datos);
                $actualizados++;
            } else {
                $producto = Producto::create([...$datos, 'codigo' => trim($fila['codigo']), 'empresa_id' => $empresaId]);
                $creados++;
            }

            $stockInicial = $decimal($fila['stock'] ?? null);
            if ($stockInicial !== null && $sucursal) {
                $stockService->ajustarA($producto, $sucursal->id, $stockInicial, 'Importación de productos');
            }
        }

        $mensaje = "Importación lista: {$creados} creados, {$actualizados} actualizados.";
        if ($errores !== []) {
            $mensaje .= ' Errores: '.implode(' ', array_slice($errores, 0, 5));
        }

        return redirect()->route('productos.index')->with($errores === [] ? 'ok' : 'error', $mensaje);
    }

    public function plantillaCsv(): \Symfony\Component\HttpFoundation\Response
    {
        $csv = "codigo;nombre;precio_venta;precio_compra;iva;categoria;unidad;stock;stock_minimo\n".
            "7790001000001;Ejemplo gaseosa 1.5L;2500;1800;21;Bebidas;UN;10;2\n";

        return response("\xEF\xBB\xBF".$csv, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => 'attachment; filename="plantilla-productos.csv"',
        ]);
    }

    public function precioMasivoForm(): View
    {
        abort_unless(auth()->user()->can('productos.precio_masivo') || auth()->user()->can('productos.editar'), 403);

        return view('productos.precio-masivo', [
            'categorias' => Categoria::orderBy('nombre')->get(),
        ]);
    }

    public function precioMasivo(Request $request): RedirectResponse
    {
        abort_unless(auth()->user()->can('productos.precio_masivo') || auth()->user()->can('productos.editar'), 403);

        $datos = $request->validate([
            'categoria_id' => ['nullable', Rule::exists('categorias', 'id')],
            'modo' => ['required', 'in:porcentaje,fijo'],
            'campo' => ['required', 'in:precio_venta,precio_compra'],
            'valor' => ['required', 'numeric'],
            'base_iva' => ['nullable', 'in:con_iva,sin_iva'],
        ], [], [
            'categoria_id' => 'categoría',
            'campo' => 'campo de precio',
            'base_iva' => 'tratamiento de IVA',
        ]);

        $baseIva = $datos['campo'] === 'precio_venta'
            ? ($datos['base_iva'] ?? 'con_iva')
            : 'con_iva';

        $query = Producto::query()->where('activo', true);
        if (! empty($datos['categoria_id'])) {
            $query->where('categoria_id', $datos['categoria_id']);
        }

        $afectados = 0;
        $query->orderBy('id')->chunkById(100, function ($productos) use ($datos, $baseIva, &$afectados) {
            foreach ($productos as $producto) {
                $actual = (float) $producto->{$datos['campo']};
                $base = $actual;

                if ($baseIva === 'sin_iva' && $datos['campo'] === 'precio_venta') {
                    $alicuota = (float) $producto->alicuota_iva;
                    $factor = 1 + ($alicuota / 100);
                    $base = $factor > 0 ? round($actual / $factor, 4) : $actual;
                }

                $nuevoBase = $datos['modo'] === 'porcentaje'
                    ? $base * (1 + ((float) $datos['valor'] / 100))
                    : $base + (float) $datos['valor'];

                if ($baseIva === 'sin_iva' && $datos['campo'] === 'precio_venta') {
                    $alicuota = (float) $producto->alicuota_iva;
                    $nuevo = round($nuevoBase * (1 + ($alicuota / 100)), 2);
                } else {
                    $nuevo = round($nuevoBase, 2);
                }

                if ($nuevo < 0) {
                    $nuevo = 0;
                }

                if (abs($nuevo - $actual) < 0.0001) {
                    continue;
                }

                $producto->update([
                    $datos['campo'] => $nuevo,
                ]);
                // Auditoría vía ProductoObserver (origen update). Reforzar origen:
                \App\Models\ProductoAuditoria::where('producto_id', $producto->id)
                    ->where('campo', $datos['campo'])
                    ->latest('id')
                    ->limit(1)
                    ->update(['origen' => 'precio_masivo']);

                $afectados++;
            }
        });

        $detalleIva = $datos['campo'] === 'precio_venta'
            ? ($baseIva === 'sin_iva' ? ' (sobre neto + IVA)' : ' (con IVA incluido)')
            : '';

        return redirect()->route('productos.index')
            ->with('ok', "Precio masivo aplicado a {$afectados} producto(s){$detalleIva}.");
    }

    public function auditoria(Producto $producto): View
    {
        abort_unless(auth()->user()->can('productos.auditoria') || auth()->user()->can('productos.editar'), 403);

        return view('productos.auditoria', [
            'producto' => $producto,
            'historial' => \App\Models\ProductoAuditoria::with('usuario')
                ->where('producto_id', $producto->id)
                ->orderByDesc('id')
                ->paginate(40),
        ]);
    }
}
