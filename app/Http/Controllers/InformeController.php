<?php

namespace App\Http\Controllers;

use App\Models\Comprobante;
use App\Models\Venta;
use App\Models\VentaPago;
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
            'total' => (float) (clone $base)->sum('total'),
            'descuentos' => (float) (clone $base)->sum('descuento'),
        ];
        $totales['promedio'] = $totales['cantidad'] > 0 ? $totales['total'] / $totales['cantidad'] : 0;

        $porDia = (clone $base)
            ->select(DB::raw('DATE(fecha) as dia'), DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(total) as total'))
            ->groupBy('dia')->orderBy('dia')->get();

        $porMedio = VentaPago::whereHas('venta', fn ($q) => $q->where('estado', 'completada')
                ->whereBetween('fecha', [$desde, $hasta]))
            ->join('medios_pago', 'medios_pago.id', '=', 'venta_pagos.medio_pago_id')
            ->select('medios_pago.nombre', DB::raw('SUM(venta_pagos.importe) as total'))
            ->groupBy('medios_pago.nombre')->orderByDesc('total')->get();

        $topProductos = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta])
            ->select('venta_items.descripcion',
                DB::raw('SUM(venta_items.cantidad) as cantidad'),
                DB::raw('SUM(venta_items.total) as total'))
            ->groupBy('venta_items.descripcion')
            ->orderByDesc('total')->limit(15)->get();

        $porVendedor = (clone $base)
            ->join('users', 'users.id', '=', 'ventas.user_id')
            ->select('users.name', DB::raw('COUNT(*) as cantidad'), DB::raw('SUM(ventas.total) as total'))
            ->groupBy('users.name')->orderByDesc('total')->get();

        return view('informes.ventas', compact('desde', 'hasta', 'totales', 'porDia', 'porMedio', 'topProductos', 'porVendedor'));
    }

    public function stock(Request $request): View
    {
        $productos = \App\Models\Producto::with(['stocks.sucursal', 'categoria'])
            ->where('activo', true)
            ->where('es_combo', false)
            ->when($request->input('filtro') === 'bajo', function ($q) {
                $q->whereRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) <= productos.stock_minimo');
            })
            ->orderBy('nombre')
            ->paginate(50)
            ->withQueryString();

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
        ]);
    }

    public function libroIva(Request $request): View|StreamedResponse
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta') ?? now();

        $comprobantes = Comprobante::with(['emisor', 'puntoVenta'])
            ->where('estado', 'autorizado')
            ->whereBetween('fecha_emision', [$desde->toDateString(), $hasta->toDateString()])
            ->orderBy('fecha_emision')->orderBy('numero')
            ->get();

        $totales = [
            'neto' => (float) $comprobantes->sum('neto'),
            'iva' => (float) $comprobantes->sum('iva'),
            'exento' => (float) $comprobantes->sum('exento'),
            'total' => (float) $comprobantes->sum('total'),
        ];

        if ($request->input('exportar') === 'csv') {
            return $this->libroIvaCsv($comprobantes, $desde->format('Ymd'), $hasta->format('Ymd'));
        }

        return view('informes.libro-iva', compact('comprobantes', 'totales', 'desde', 'hasta'));
    }

    public function cuentasCorrientes(Request $request): View
    {
        $clientes = \App\Models\Cliente::where('activo', true)
            ->withSum('movimientosCuenta as saldo', 'importe')
            ->when($request->input('filtro') === 'con_saldo', fn ($q) => $q->having('saldo', '>', 0))
            ->when($request->input('filtro') === 'a_favor', fn ($q) => $q->having('saldo', '<', 0))
            ->orderByDesc('saldo')
            ->paginate(50)
            ->withQueryString();

        $morphCliente = (new \App\Models\Cliente)->getMorphClass();
        $totales = [
            'saldo_neto' => (float) \App\Models\MovimientoCuenta::where('titular_type', $morphCliente)->sum('importe'),
            'clientes_listados' => $clientes->total(),
        ];

        return view('informes.cuentas-corrientes', compact('clientes', 'totales'));
    }

    public function cajas(Request $request): View
    {
        $desde = $request->date('desde') ?? now()->startOfMonth();
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        $sesiones = \App\Models\CajaSesion::with(['caja', 'usuario'])
            ->whereBetween('abierta_at', [$desde, $hasta])
            ->withCount(['ventas as ventas_count' => fn ($q) => $q->where('estado', 'completada')])
            ->withSum(['ventas as ventas_total' => fn ($q) => $q->where('estado', 'completada')], 'total')
            ->orderByDesc('abierta_at')
            ->paginate(25)
            ->withQueryString();

        $totales = [
            'sesiones' => $sesiones->total(),
            'ventas' => (float) \App\Models\Venta::where('estado', 'completada')
                ->whereBetween('fecha', [$desde, $hasta])
                ->whereNotNull('caja_sesion_id')
                ->sum('total'),
        ];

        return view('informes.cajas', compact('sesiones', 'totales', 'desde', 'hasta'));
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
