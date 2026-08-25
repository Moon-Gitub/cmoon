<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\Producto;
use App\Models\User;
use App\Models\Venta;
use App\Models\VentaPago;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class InformeService
{
    /**
     * @return array{0: Carbon, 1: Carbon}
     */
    public function rangoFechas(Request $request, ?Carbon $defaultDesde = null): array
    {
        $desde = $request->date('desde') ?? ($defaultDesde ?? now()->startOfMonth());
        $hasta = $request->date('hasta')?->endOfDay() ?? now()->endOfDay();

        if ($desde->gt($hasta)) {
            [$desde, $hasta] = [$hasta->copy()->startOfDay(), $desde->copy()->endOfDay()];
        }

        return [$desde->copy()->startOfDay(), $hasta];
    }

    /**
     * @return array{user_id: ?int, sucursal_id: ?int, medio_pago_id: ?int}
     */
    public function filtrosVentas(Request $request): array
    {
        return [
            'user_id' => $request->filled('vendedor') ? $request->integer('vendedor') : null,
            'sucursal_id' => $request->filled('sucursal') ? $request->integer('sucursal') : null,
            'medio_pago_id' => $request->filled('medio_pago') ? $request->integer('medio_pago') : null,
        ];
    }

    public function queryVentas(Carbon $desde, Carbon $hasta, array $filtros = [])
    {
        $q = Venta::query()
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta]);

        if (! empty($filtros['user_id'])) {
            $q->where('ventas.user_id', $filtros['user_id']);
        }
        if (! empty($filtros['sucursal_id'])) {
            $q->where('ventas.sucursal_id', $filtros['sucursal_id']);
        }
        if (! empty($filtros['medio_pago_id'])) {
            $q->whereExists(function ($sub) use ($filtros) {
                $sub->select(DB::raw(1))
                    ->from('venta_pagos')
                    ->whereColumn('venta_pagos.venta_id', 'ventas.id')
                    ->where('venta_pagos.medio_pago_id', $filtros['medio_pago_id']);
            });
        }

        return $q;
    }

    public function kpisVentas(Carbon $desde, Carbon $hasta, array $filtros = []): array
    {
        $base = $this->queryVentas($desde, $hasta, $filtros);
        $cantidad = (clone $base)->count();
        $total = (float) (clone $base)->sum(DB::raw(Venta::sqlTotalConSigno()));
        $descuentos = (float) (clone $base)->sum('descuento');
        $clientes = (int) (clone $base)->whereNotNull('cliente_id')->distinct()->count('cliente_id');

        $dias = max(1, $desde->diffInDays($hasta) + 1);
        $prevHasta = $desde->copy()->subDay()->endOfDay();
        $prevDesde = $prevHasta->copy()->subDays($dias - 1)->startOfDay();
        $totalPrev = (float) $this->queryVentas($prevDesde, $prevHasta, $filtros)
            ->sum(DB::raw(Venta::sqlTotalConSigno()));

        $variacion = $totalPrev > 0
            ? (($total - $totalPrev) / $totalPrev) * 100
            : ($total > 0 ? 100.0 : 0.0);

        return [
            'cantidad' => $cantidad,
            'total' => $total,
            'descuentos' => $descuentos,
            'promedio' => $cantidad > 0 ? $total / $cantidad : 0.0,
            'clientes' => $clientes,
            'total_previo' => $totalPrev,
            'variacion_pct' => round($variacion, 1),
            'periodo_previo' => [$prevDesde, $prevHasta],
        ];
    }

    public function ventasPorDia(Carbon $desde, Carbon $hasta, array $filtros = []): Collection
    {
        return $this->queryVentas($desde, $hasta, $filtros)
            ->select(
                DB::raw('DATE(ventas.fecha) as dia'),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM('.Venta::sqlTotalConSigno().') as total')
            )
            ->groupBy('dia')
            ->orderBy('dia')
            ->get();
    }

    public function ventasPorMedio(Carbon $desde, Carbon $hasta, array $filtros = []): Collection
    {
        $q = VentaPago::query()
            ->join('ventas', 'ventas.id', '=', 'venta_pagos.venta_id')
            ->join('medios_pago', 'medios_pago.id', '=', 'venta_pagos.medio_pago_id')
            ->where('ventas.empresa_id', auth()->user()->empresa_id)
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta]);

        if (! empty($filtros['user_id'])) {
            $q->where('ventas.user_id', $filtros['user_id']);
        }
        if (! empty($filtros['sucursal_id'])) {
            $q->where('ventas.sucursal_id', $filtros['sucursal_id']);
        }
        if (! empty($filtros['medio_pago_id'])) {
            $q->where('venta_pagos.medio_pago_id', $filtros['medio_pago_id']);
        }

        return $q
            ->select('medios_pago.nombre', DB::raw('SUM('.Venta::sqlImportePagoConSigno().') as total'))
            ->groupBy('medios_pago.nombre')
            ->orderByDesc('total')
            ->get();
    }

    public function ventasPorVendedor(Carbon $desde, Carbon $hasta, array $filtros = []): Collection
    {
        return $this->queryVentas($desde, $hasta, $filtros)
            ->join('users', 'users.id', '=', 'ventas.user_id')
            ->select(
                'users.id',
                'users.name',
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM('.Venta::sqlTotalConSigno().') as total')
            )
            ->groupBy('users.id', 'users.name')
            ->orderByDesc('total')
            ->get();
    }

    public function ventasPorSucursal(Carbon $desde, Carbon $hasta, array $filtros = []): Collection
    {
        return $this->queryVentas($desde, $hasta, $filtros)
            ->leftJoin('sucursales', 'sucursales.id', '=', 'ventas.sucursal_id')
            ->select(
                DB::raw("COALESCE(sucursales.nombre, 'Sin sucursal') as nombre"),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM('.Venta::sqlTotalConSigno().') as total')
            )
            ->groupBy(DB::raw("COALESCE(sucursales.nombre, 'Sin sucursal')"))
            ->orderByDesc('total')
            ->get();
    }

    public function ventasPorCliente(Carbon $desde, Carbon $hasta, array $filtros = [], int $limit = 20): Collection
    {
        return $this->queryVentas($desde, $hasta, $filtros)
            ->leftJoin('clientes', 'clientes.id', '=', 'ventas.cliente_id')
            ->select(
                DB::raw("COALESCE(clientes.nombre, 'Consumidor final') as nombre"),
                DB::raw('COUNT(*) as cantidad'),
                DB::raw('SUM('.Venta::sqlTotalConSigno().') as total')
            )
            ->groupBy(DB::raw("COALESCE(clientes.nombre, 'Consumidor final')"))
            ->orderByDesc('total')
            ->limit($limit)
            ->get();
    }

    /**
     * Productos vendidos con costo estimado (precio_compra actual del producto).
     */
    public function productosVendidos(Carbon $desde, Carbon $hasta, array $filtros = [], int $limit = 500): Collection
    {
        $q = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->leftJoin('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->where('ventas.empresa_id', auth()->user()->empresa_id)
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta]);

        if (! empty($filtros['user_id'])) {
            $q->where('ventas.user_id', $filtros['user_id']);
        }
        if (! empty($filtros['sucursal_id'])) {
            $q->where('ventas.sucursal_id', $filtros['sucursal_id']);
        }
        if (! empty($filtros['medio_pago_id'])) {
            $q->whereExists(function ($sub) use ($filtros) {
                $sub->select(DB::raw(1))
                    ->from('venta_pagos')
                    ->whereColumn('venta_pagos.venta_id', 'ventas.id')
                    ->where('venta_pagos.medio_pago_id', $filtros['medio_pago_id']);
            });
        }

        $cant = Venta::sqlCantidadItemConSigno();
        $tot = Venta::sqlTotalItemConSigno();

        return $q
            ->select(
                DB::raw('COALESCE(productos.id, 0) as producto_id'),
                DB::raw('COALESCE(productos.codigo, "") as codigo'),
                DB::raw('COALESCE(productos.nombre, venta_items.descripcion) as nombre'),
                DB::raw('COALESCE(categorias.nombre, "Sin categoría") as categoria'),
                DB::raw("SUM({$cant}) as cantidad"),
                DB::raw("SUM({$tot}) as venta"),
                DB::raw("SUM({$cant} * COALESCE(productos.precio_compra, 0)) as costo"),
            )
            ->groupBy(
                DB::raw('COALESCE(productos.id, 0)'),
                DB::raw('COALESCE(productos.codigo, "")'),
                DB::raw('COALESCE(productos.nombre, venta_items.descripcion)'),
                DB::raw('COALESCE(categorias.nombre, "Sin categoría")'),
            )
            ->orderByDesc('venta')
            ->limit($limit)
            ->get()
            ->map(function ($row) {
                $venta = (float) $row->venta;
                $costo = (float) $row->costo;
                $margen = $venta - $costo;
                $row->margen = $margen;
                $row->margen_pct = $venta > 0 ? round(($margen / $venta) * 100, 1) : 0.0;

                return $row;
            });
    }

    public function ventasPorCategoria(Carbon $desde, Carbon $hasta, array $filtros = []): Collection
    {
        $cant = Venta::sqlCantidadItemConSigno();
        $tot = Venta::sqlTotalItemConSigno();

        $q = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->leftJoin('productos', 'productos.id', '=', 'venta_items.producto_id')
            ->leftJoin('categorias', 'categorias.id', '=', 'productos.categoria_id')
            ->where('ventas.empresa_id', auth()->user()->empresa_id)
            ->where('ventas.estado', 'completada')
            ->whereBetween('ventas.fecha', [$desde, $hasta]);

        if (! empty($filtros['user_id'])) {
            $q->where('ventas.user_id', $filtros['user_id']);
        }
        if (! empty($filtros['sucursal_id'])) {
            $q->where('ventas.sucursal_id', $filtros['sucursal_id']);
        }

        $totalGeneral = (float) (clone $q)->selectRaw("COALESCE(SUM({$tot}), 0) as t")->value('t');

        return $q
            ->select(
                DB::raw('COALESCE(categorias.nombre, "Sin categoría") as nombre'),
                DB::raw("SUM({$cant}) as cantidad"),
                DB::raw("SUM({$tot}) as total"),
            )
            ->groupBy(DB::raw('COALESCE(categorias.nombre, "Sin categoría")'))
            ->orderByDesc('total')
            ->get()
            ->map(function ($row) use ($totalGeneral) {
                $row->porcentaje = $totalGeneral > 0
                    ? round(((float) $row->total / $totalGeneral) * 100, 1)
                    : 0.0;
                $row->promedio = (float) $row->cantidad > 0
                    ? (float) $row->total / (float) $row->cantidad
                    : 0.0;

                return $row;
            });
    }

    public function rentabilidad(Carbon $desde, Carbon $hasta, array $filtros = []): array
    {
        $productos = $this->productosVendidos($desde, $hasta, $filtros, 1000);
        $venta = (float) $productos->sum('venta');
        $costo = (float) $productos->sum('costo');
        $margenBruto = $venta - $costo;

        $sesionIds = CajaSesion::query()
            ->whereBetween('abierta_at', [$desde, $hasta])
            ->pluck('id');

        $gastosCaja = (float) CajaMovimiento::query()
            ->whereIn('caja_sesion_id', $sesionIds)
            ->where('tipo', 'egreso')
            ->sum('importe');

        $margenNeto = $margenBruto - $gastosCaja;
        $margenPct = $venta > 0 ? round(($margenBruto / $venta) * 100, 1) : 0.0;

        $nivel = match (true) {
            $margenPct >= 40 => 'Excelente',
            $margenPct >= 25 => 'Buena',
            $margenPct >= 15 => 'Aceptable',
            $margenPct >= 0 => 'Baja',
            default => 'Pérdida',
        };

        return [
            'venta' => $venta,
            'costo' => $costo,
            'margen_bruto' => $margenBruto,
            'margen_pct' => $margenPct,
            'gastos_caja' => $gastosCaja,
            'margen_neto' => $margenNeto,
            'nivel' => $nivel,
            'top_productos' => $productos->sortByDesc('margen')->take(20)->values(),
            'por_cliente' => $this->ventasPorCliente($desde, $hasta, $filtros, 20),
        ];
    }

    /**
     * ¿Qué debo comprar? — velocidad de venta + cobertura + ROI.
     *
     * @return array{items: Collection, resumen: array<string, float|int>}
     */
    public function gestionPedidos(int $diasAnalisis = 30, int $diasCobertura = 30): array
    {
        $diasAnalisis = max(7, min(90, $diasAnalisis));
        $diasCobertura = max(7, min(90, $diasCobertura));
        $desde = now()->subDays($diasAnalisis)->startOfDay();
        $desde7 = now()->subDays(7)->startOfDay();
        $cant = Venta::sqlCantidadItemConSigno();

        $agg = DB::table('venta_items')
            ->join('ventas', 'ventas.id', '=', 'venta_items.venta_id')
            ->where('ventas.empresa_id', auth()->user()->empresa_id)
            ->where('ventas.estado', 'completada')
            ->whereNotNull('venta_items.producto_id')
            ->where('ventas.fecha', '>=', $desde)
            ->select(
                'venta_items.producto_id',
                DB::raw("SUM(CASE WHEN ventas.fecha >= '{$desde7->toDateTimeString()}' THEN {$cant} ELSE 0 END) as ventas_7"),
                DB::raw("SUM({$cant}) as ventas_periodo"),
            )
            ->groupBy('venta_items.producto_id')
            ->having('ventas_periodo', '>', 0)
            ->get()
            ->keyBy('producto_id');

        $productos = Producto::query()
            ->withSum('stocks as stock_total', 'cantidad')
            ->whereIn('id', $agg->keys())
            ->where('activo', true)
            ->where('es_combo', false)
            ->get();

        $items = $productos->map(function (Producto $p) use ($agg, $diasAnalisis, $diasCobertura) {
            $row = $agg->get($p->id);
            $ventasPeriodo = (float) ($row->ventas_periodo ?? 0);
            $ventas7 = (float) ($row->ventas_7 ?? 0);
            $promedio = $diasAnalisis > 0 ? $ventasPeriodo / $diasAnalisis : 0;
            $stock = max(0, (float) ($p->stock_total ?? 0));
            $cobertura = $promedio > 0 ? round($stock / $promedio, 1) : 999.0;
            $sugerida = max(0, round(($promedio * $diasCobertura) - $stock, 2));
            $compra = (float) $p->precio_compra;
            $venta = (float) $p->precio_venta;
            $inversion = $sugerida * $compra;
            $ganancia = $sugerida * ($venta - $compra);
            $roi = $inversion > 0 ? round(($ganancia / $inversion) * 100, 1) : 0.0;
            $estado = $cobertura <= 3 ? 'critico' : ($cobertura <= 7 ? 'urgente' : 'normal');

            return (object) [
                'id' => $p->id,
                'codigo' => $p->codigo,
                'nombre' => $p->nombre,
                'stock' => $stock,
                'stock_minimo' => (float) $p->stock_minimo,
                'precio_compra' => $compra,
                'precio_venta' => $venta,
                'ventas_7' => $ventas7,
                'ventas_periodo' => $ventasPeriodo,
                'promedio_diario' => round($promedio, 2),
                'dias_cobertura' => $cobertura,
                'cantidad_sugerida' => $sugerida,
                'inversion' => round($inversion, 2),
                'ganancia' => round($ganancia, 2),
                'roi' => $roi,
                'estado' => $estado,
            ];
        })->sortBy('dias_cobertura')->values();

        $conPedido = $items->filter(fn ($i) => $i->cantidad_sugerida > 0);

        return [
            'items' => $items,
            'resumen' => [
                'productos' => $items->count(),
                'criticos' => $items->where('estado', 'critico')->count(),
                'urgentes' => $items->where('estado', 'urgente')->count(),
                'inversion_total' => round((float) $conPedido->sum('inversion'), 2),
                'inversion_criticos' => round((float) $items->where('estado', 'critico')->sum('inversion'), 2),
                'ganancia_esperada' => round((float) $conPedido->sum('ganancia'), 2),
            ],
        ];
    }

    public function stockValorizado(): array
    {
        $valorizado = DB::table('stocks')
            ->join('productos', 'productos.id', '=', 'stocks.producto_id')
            ->whereNull('productos.deleted_at')
            ->where('productos.activo', true)
            ->where('productos.es_combo', false)
            ->where('productos.empresa_id', auth()->user()->empresa_id)
            ->selectRaw('
                COUNT(DISTINCT productos.id) as items,
                SUM(stocks.cantidad) as unidades,
                SUM(stocks.cantidad * productos.precio_compra) as costo,
                SUM(stocks.cantidad * productos.precio_venta) as venta
            ')
            ->first();

        $bajo = Producto::query()
            ->where('activo', true)
            ->where('es_combo', false)
            ->whereRaw('(select coalesce(sum(cantidad), 0) from stocks where stocks.producto_id = productos.id) <= productos.stock_minimo')
            ->count();

        $costo = (float) ($valorizado->costo ?? 0);
        $venta = (float) ($valorizado->venta ?? 0);

        return [
            'items' => (int) ($valorizado->items ?? 0),
            'unidades' => (float) ($valorizado->unidades ?? 0),
            'costo' => $costo,
            'venta' => $venta,
            'margen_potencial' => $venta - $costo,
            'bajo_minimo' => $bajo,
        ];
    }

    public function vendedoresActivos(): Collection
    {
        return User::query()
            ->where('empresa_id', auth()->user()->empresa_id)
            ->where('activo', true)
            ->orderBy('name')
            ->get(['id', 'name']);
    }
}
