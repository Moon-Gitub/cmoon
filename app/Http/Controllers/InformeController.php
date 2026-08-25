<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\Venta;
use App\Models\VentaPago;
use App\Support\TableSort;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InformeController extends Controller
{
    public function ventas(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        $base = Venta::where('estado', 'completada')->whereBetween('fecha', [$desde, $hasta]);

        $totales = [
            'cantidad' => (clone $base)->count(),
            'total' => (float) (clone $base)->sum(DB::raw(Venta::sqlTotalConSigno())),
            'descuentos' => (float) (clone $base)->sum('descuento'),
        ];
        $totales['promedio'] = $totales['cantidad'] > 0 ? $totales['total'] / $totales['cantidad'] : 0;

        $porDia = (clone $base)
            ->select(DB::raw('DATE(fecha) as dia'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM('.Venta::sqlTotalConSigno().') as total'))
            ->groupBy('dia')->orderBy('dia')->get();

        $porMedio = VentaPago::query()
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('medios_pago', 'medios_pago.id', '=', 'venta_pagos.medio_pago_id')
            ->where('ventas.empresa_id', auth()->user()->empresa_id)
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->select('medios_pago.nombre', DB::raw('SUM('.Venta::sqlImportePagoConSigno().') as total'))
            ->groupBy('medios_pago.nombre')->orderByDesc('total')->get();

        $topProductos = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->select('venta_items.descripcion',
                DB::raw('SUM('.Venta::sqlCantidadItemConSigno().') as cantidad'),
                DB::raw('SUM('.Venta::sqlTotalItemConSigno().') as total'))
            ->groupBy('venta_items.descripcion')
            ->orderByDesc('total')->limit(15)->get();

        $porVendedor = (clone $base)
            ->join('users', 'users.id', '=', 'ventas.user_id')
            ->select('users.name', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM('.Venta::sqlTotalConSigno().') as total'))
            ->groupBy('users.name')->orderByDesc('total')->get();

        return view('informes.ventas', compact('desde', 'hasta', 'totales', 'porDia', 'porMedio', 'topProductos', 'porVendedor'));
    }

    public function stock(Request $request): View
    {
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
        ], 'producto');

        $productos = $query->paginate(50)->withQueryString();

        $valorizado = DB::table('stocks')
            ->join('productos', 'productos.id', '=', 'stocks.producto_id')
            ->whereNull('productos.deleted_at')
            ->where('productos.activo', true)
            ->selectRaw('SUM(stocks.cantidad * productos.precio_compra) as costo, SUM(stocks.cantidad * productos.precio_venta) as venta')
            ->first();

        return view('informes.stock', [
            'productos' => $productos,
            'valorCosto' => (float) ($valorizado->costo ?? 0),
            'valorVenta' => (float) ($valorizado->venta ?? 0),
            'sucursales' => \App\Models\Sucursal::where('activa', true)->get(),
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

    public function cuentasCorrientes(Request $request): View
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
        return response()->streamDownload(function () use ($comprobantes) {
            $salida = fopen('php://output', 'w');
            // BOM para que Excel abra bien los acentos
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, ['Fecha', 'Tipo', 'Número', 'Receptor', 'Doc', 'Neto', 'IVA', 'Exento', 'Total', 'CAE'], ';');

            foreach ($comprobantes as $c) {
                fputcsv($salida, [
                    $c->fecha_emision->format('d/m/Y'),
                    $c->tipoNombre(),
                    $c->numeroFormateado(),
                    $c->receptor_nombre,
                    $c->doc_numero,
                    number_format((float) $c->neto, 2, ',', ''),
                    number_format((float) $c->iva, 2, ',', ''),
                    number_format((float) $c->exento, 2, ',', ''),
                    number_format((float) $c->total, 2, ',', ''),
                    $c->cae,
                ], ';');
            }

            fclose($salida);
        }, "libro-iva-ventas-{$desde}-{$hasta}.csv", ['Content-Type' => 'text/csv; charset=UTF-8']);
    }
}
