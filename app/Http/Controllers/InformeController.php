<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\MedioPago;
use App\Models\Sucursal;
use App\Models\Venta;
use App\Services\InformeService;
use App\Support\CsvExport;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformeController extends Controller
{
    public function __construct(private readonly InformeService $informes) {}

    public function index(): View
    {
        [$desde, $hasta] = [now()->startOfMonth(), now()->endOfDay()];
        $kpis = $this->informes->kpisVentas($desde, $hasta);
        $stock = $this->informes->stockValorizado();
        $pedidos = $this->informes->gestionPedidos(30, 30);

        return view('informes.index', compact('kpis', 'stock', 'pedidos', 'desde', 'hasta'));
    }

    public function ventas(Request $request): View|StreamedResponse
    {
        [$desde, $hasta] = $this->informes->rangoFechas($request);
        $filtros = $this->informes->filtrosVentas($request);

        $totales = $this->informes->kpisVentas($desde, $hasta, $filtros);
        $porDia = $this->informes->ventasPorDia($desde, $hasta, $filtros);
        $porMedio = $this->informes->ventasPorMedio($desde, $hasta, $filtros);
        $porVendedor = $this->informes->ventasPorVendedor($desde, $hasta, $filtros);
        $porSucursal = $this->informes->ventasPorSucursal($desde, $hasta, $filtros);
        $porCliente = $this->informes->ventasPorCliente($desde, $hasta, $filtros, 15);
        $topProductos = $this->informes->productosVendidos($desde, $hasta, $filtros, 15);

        if ($request->input('exportar') === 'csv') {
            return CsvExport::download(
                'informe-ventas-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.csv',
                ['Día', 'Cantidad', 'Total'],
                $porDia->map(fn ($d) => [
                    $d->dia,
                    $d->cantidad,
                    CsvExport::money((float) $d->total),
                ])
            );
        }

        return view('informes.ventas', [
            'desde' => $desde,
            'hasta' => $hasta,
            'filtros' => $filtros,
            'totales' => $totales,
            'porDia' => $porDia,
            'porMedio' => $porMedio,
            'porVendedor' => $porVendedor,
            'porSucursal' => $porSucursal,
            'porCliente' => $porCliente,
            'topProductos' => $topProductos,
            'vendedores' => $this->informes->vendedoresActivos(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
            'mediosPago' => MedioPago::where('activo', true)->orderBy('nombre')->get(),
        ]);
    }

    public function productos(Request $request): View|StreamedResponse
    {
        [$desde, $hasta] = $this->informes->rangoFechas($request);
        $filtros = $this->informes->filtrosVentas($request);
        $productos = $this->informes->productosVendidos($desde, $hasta, $filtros);

        $resumen = [
            'unidades' => (float) $productos->sum('cantidad'),
            'venta' => (float) $productos->sum('venta'),
            'costo' => (float) $productos->sum('costo'),
            'margen' => (float) $productos->sum('margen'),
            'items' => $productos->count(),
        ];
        $resumen['margen_pct'] = $resumen['venta'] > 0
            ? round(($resumen['margen'] / $resumen['venta']) * 100, 1)
            : 0.0;

        if ($request->input('exportar') === 'csv') {
            return CsvExport::download(
                'productos-vendidos-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.csv',
                ['Código', 'Producto', 'Categoría', 'Cantidad', 'Costo', 'Venta', 'Margen', '%'],
                $productos->map(fn ($p) => [
                    $p->codigo,
                    $p->nombre,
                    $p->categoria,
                    CsvExport::qty((float) $p->cantidad),
                    CsvExport::money((float) $p->costo),
                    CsvExport::money((float) $p->venta),
                    CsvExport::money((float) $p->margen),
                    number_format((float) $p->margen_pct, 1, ',', '').'%',
                ])
            );
        }

        return view('informes.productos', [
            'desde' => $desde,
            'hasta' => $hasta,
            'filtros' => $filtros,
            'productos' => $productos,
            'resumen' => $resumen,
            'vendedores' => $this->informes->vendedoresActivos(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function rentabilidad(Request $request): View|StreamedResponse
    {
        [$desde, $hasta] = $this->informes->rangoFechas($request);
        $filtros = $this->informes->filtrosVentas($request);
        $data = $this->informes->rentabilidad($desde, $hasta, $filtros);

        if ($request->input('exportar') === 'csv') {
            return CsvExport::download(
                'rentabilidad-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.csv',
                ['Código', 'Producto', 'Cantidad', 'Costo', 'Venta', 'Margen', '%'],
                $data['top_productos']->map(fn ($p) => [
                    $p->codigo,
                    $p->nombre,
                    CsvExport::qty((float) $p->cantidad),
                    CsvExport::money((float) $p->costo),
                    CsvExport::money((float) $p->venta),
                    CsvExport::money((float) $p->margen),
                    number_format((float) $p->margen_pct, 1, ',', '').'%',
                ])
            );
        }

        return view('informes.rentabilidad', [
            'desde' => $desde,
            'hasta' => $hasta,
            'filtros' => $filtros,
            'data' => $data,
            'vendedores' => $this->informes->vendedoresActivos(),
            'sucursales' => Sucursal::where('activa', true)->orderBy('nombre')->get(),
        ]);
    }

    public function categorias(Request $request): View|StreamedResponse
    {
        [$desde, $hasta] = $this->informes->rangoFechas($request);
        $filtros = $this->informes->filtrosVentas($request);
        $filas = $this->informes->ventasPorCategoria($desde, $hasta, $filtros);
        $total = (float) $filas->sum('total');

        if ($request->input('exportar') === 'csv') {
            return CsvExport::download(
                'ventas-categorias-'.$desde->format('Ymd').'-'.$hasta->format('Ymd').'.csv',
                ['Categoría', 'Cantidad', 'Total', 'Promedio', '%'],
                $filas->map(fn ($f) => [
                    $f->nombre,
                    CsvExport::qty((float) $f->cantidad),
                    CsvExport::money((float) $f->total),
                    CsvExport::money((float) $f->promedio),
                    number_format((float) $f->porcentaje, 1, ',', '').'%',
                ])
            );
        }

        return view('informes.categorias', compact('desde', 'hasta', 'filtros', 'filas', 'total'));
    }

    public function pedidos(Request $request): View|StreamedResponse
    {
        $diasAnalisis = max(7, min(90, $request->integer('dias_analisis', 30) ?: 30));
        $diasCobertura = max(7, min(90, $request->integer('dias_cobertura', 30) ?: 30));
        $soloCriticos = $request->boolean('solo_pedir');

        $data = $this->informes->gestionPedidos($diasAnalisis, $diasCobertura);
        $items = $soloCriticos
            ? $data['items']->filter(fn ($i) => $i->cantidad_sugerida > 0)->values()
            : $data['items'];

        if ($request->input('exportar') === 'csv') {
            return CsvExport::download(
                'gestion-pedidos.csv',
                ['Estado', 'Código', 'Producto', 'Stock', 'Venta/día', 'Cobertura', 'Pedir', 'Inversión', 'Ganancia', 'ROI %'],
                $items->map(fn ($i) => [
                    $i->estado,
                    $i->codigo,
                    $i->nombre,
                    CsvExport::qty((float) $i->stock),
                    CsvExport::qty((float) $i->promedio_diario),
                    $i->dias_cobertura,
                    CsvExport::qty((float) $i->cantidad_sugerida),
                    CsvExport::money((float) $i->inversion),
                    CsvExport::money((float) $i->ganancia),
                    number_format((float) $i->roi, 1, ',', ''),
                ])
            );
        }

        return view('informes.pedidos', [
            'diasAnalisis' => $diasAnalisis,
            'diasCobertura' => $diasCobertura,
            'soloCriticos' => $soloCriticos,
            'items' => $items,
            'resumen' => $data['resumen'],
        ]);
    }

    public function stock(Request $request): View|StreamedResponse
    {
        $valorizado = $this->informes->stockValorizado();

        $query = \App\Models\Producto::with(['stocks.sucursal', 'categoria'])
            ->withSum('stocks as stock_total', 'cantidad')
            ->where('activo', true)
            ->where('es_combo', false)
            ->when($request->input('filtro') === 'bajo', function ($q) {
                $q->whereRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) <= productos.stock_minimo');
            });

        [$sort, $dir] = TableSort::apply($query, $request, [
            'producto' => 'nombre',
            'categoria' => fn ($q, $d) => $q->orderBy(
                \App\Models\Categoria::select('nombre')->whereColumn('categorias.id', 'productos.categoria_id'),
                $d
            ),
            'total' => 'stock_total',
            'minimo' => 'stock_minimo',
            'valor_costo' => fn ($q, $d) => $q->orderByRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) * productos.precio_compra '.$d),
            'valor_venta' => fn ($q, $d) => $q->orderByRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) * productos.precio_venta '.$d),
        ], 'producto');

        if ($request->input('exportar') === 'csv') {
            $rows = (clone $query)->limit(5000)->get();

            return CsvExport::download(
                'stock-valorizado.csv',
                ['Código', 'Producto', 'Categoría', 'Stock', 'Mínimo', 'P.compra', 'Valorizado costo', 'P.venta', 'Valorizado venta'],
                $rows->map(function ($p) {
                    $stk = (float) ($p->stock_total ?? $p->stockTotal());

                    return [
                        $p->codigo,
                        $p->nombre,
                        $p->categoria?->nombre ?? '',
                        CsvExport::qty($stk),
                        CsvExport::qty((float) $p->stock_minimo),
                        CsvExport::money((float) $p->precio_compra),
                        CsvExport::money($stk * (float) $p->precio_compra),
                        CsvExport::money((float) $p->precio_venta),
                        CsvExport::money($stk * (float) $p->precio_venta),
                    ];
                })
            );
        }

        $productos = $query->paginate(50)->withQueryString();

        return view('informes.stock', [
            'productos' => $productos,
            'valorCosto' => $valorizado['costo'],
            'valorVenta' => $valorizado['venta'],
            'valorizado' => $valorizado,
            'sucursales' => Sucursal::where('activa', true)->get(),
            'sort' => $sort,
            'dir' => $dir,
        ]);
    }

    public function libroIva(Request $request): View|StreamedResponse
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $query = Comprobante::with(['emisor', 'puntoVenta'])
            ->where('estado', 'autorizado')
            ->whereBetween('fecha_emision', [$desde->toDateString(), $hasta->toDateString()]);

        [$sort, $dir] = TableSort::apply($query, $request, [
            'fecha' => 'fecha_emision',
            'comprobante' => 'numero',
            'receptor' => 'receptor_nombre',
            'neto' => 'neto',
            'iva' => 'iva',
            'total' => 'total',
            'cae' => 'cae',
        ], 'fecha', 'asc');

        if ($sort === 'fecha') {
            $query->orderBy('numero');
        }

        $comprobantes = $query->get();

        $totales = [
            'neto' => (float) $comprobantes->sum('neto'),
            'iva' => (float) $comprobantes->sum('iva'),
            'exento' => (float) $comprobantes->sum('exento'),
            'total' => (float) $comprobantes->sum('total'),
        ];

        if ($request->input('exportar') === 'csv') {
            return $this->libroIvaCsv($comprobantes, $desde->format('Ymd'), $hasta->format('Ymd'));
        }

        return view('informes.libro-iva', compact('comprobantes', 'totales', 'desde', 'hasta', 'sort', 'dir'));
    }

    public function cuentasCorrientes(Request $request): View|StreamedResponse
    {
        $query = \App\Models\Cliente::where('activo', true)
            ->withSum('movimientosCuenta as saldo', 'importe')
            ->when($request->input('filtro') === 'con_saldo', fn ($q) => $q->having('saldo', '>', 0))
            ->when($request->input('filtro') === 'a_favor', fn ($q) => $q->having('saldo', '<', 0));

        [$sort, $dir] = TableSort::apply($query, $request, [
            'cliente' => 'nombre',
            'documento' => 'documento',
            'saldo' => 'saldo',
        ], 'saldo', 'desc');

        if ($request->input('exportar') === 'csv') {
            $rows = (clone $query)->limit(5000)->get();

            return CsvExport::download(
                'cuentas-corrientes.csv',
                ['Cliente', 'Documento', 'Saldo'],
                $rows->map(fn ($c) => [
                    $c->nombre,
                    $c->documento,
                    CsvExport::money((float) ($c->saldo ?? 0)),
                ])
            );
        }

        $clientes = $query->paginate(50)->withQueryString();

        $morphCliente = (new \App\Models\Cliente)->getMorphClass();
        $totales = [
            'saldo_neto' => (float) \App\Models\MovimientoCuenta::where('titular_type', $morphCliente)->sum('importe'),
            'clientes_listados' => $clientes->total(),
        ];

        return view('informes.cuentas-corrientes', compact('clientes', 'totales', 'sort', 'dir'));
    }

    public function cajas(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        $query = \App\Models\CajaSesion::with(['caja', 'usuario'])
            ->whereBetween('abierta_at', [$desde, $hasta])
            ->withCount(['ventas as ventas_count' => fn ($q) => $q->where('estado', 'completada')])
            ->addSelect([
                'ventas_total' => Venta::query()
                    ->selectRaw('COALESCE(SUM('.Venta::sqlTotalConSigno().'), 0)')
                    ->whereColumn('ventas.caja_sesion_id', 'caja_sesiones.id')
                    ->where('ventas.estado', 'completada'),
            ]);

        [$sort, $dir] = TableSort::apply($query, $request, [
            'caja' => fn ($q, $d) => $q->orderBy(
                \App\Models\Caja::select('nombre')->whereColumn('cajas.id', 'caja_sesiones.caja_id'),
                $d
            ),
            'cajero' => fn ($q, $d) => $q->orderBy(
                \App\Models\User::select('name')->whereColumn('users.id', 'caja_sesiones.user_id'),
                $d
            ),
            'apertura' => 'abierta_at',
            'cierre' => 'cerrada_at',
            'ventas' => 'ventas_count',
            'total' => 'ventas_total',
            'estado' => 'estado',
        ], 'apertura', 'desc');

        $sesiones = $query->paginate(25)->withQueryString();

        $totales = [
            'sesiones' => $sesiones->total(),
            'ventas' => (float) \App\Models\Venta::where('estado', 'completada')
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotNull('caja_sesion_id')
                ->sum(DB::raw(Venta::sqlTotalConSigno())),
        ];

        return view('informes.cajas', compact('sesiones', 'totales', 'desde', 'hasta', 'sort', 'dir'));
    }

    private function libroIvaCsv($comprobantes, string $desde, string $hasta): StreamedResponse
    {
        return CsvExport::download(
            "libro-iva-ventas-{$desde}-{$hasta}.csv",
            ['Fecha', 'Tipo', 'Número', 'Receptor', 'Doc', 'Neto', 'IVA', 'Exento', 'Total', 'CAE'],
            $comprobantes->map(fn ($c) => [
                $c->fecha_emision->format('d/m/Y'),
                $c->tipoNombre(),
                $c->numeroFormateado(),
                $c->receptor_nombre,
                $c->doc_numero,
                CsvExport::money((float) $c->neto),
                CsvExport::money((float) $c->iva),
                CsvExport::money((float) $c->exento),
                CsvExport::money((float) $c->total),
                $c->cae,
            ])
        );
    }
}
