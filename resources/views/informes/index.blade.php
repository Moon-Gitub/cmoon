@extends('layouts.app')

@section('titulo', 'Informes')

@section('contenido')
    @include('informes._nav')

    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Centro de informes</h1>
        <p class="mt-1 text-sm text-slate-500">
            Resumen del mes en curso ({{ $desde->format('d/m/Y') }} – {{ $hasta->format('d/m/Y') }}) y accesos a cada análisis.
        </p>
    </div>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ventas del mes</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($kpis['total'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs {{ $kpis['variacion_pct'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $kpis['variacion_pct'] >= 0 ? '+' : '' }}{{ number_format($kpis['variacion_pct'], 1, ',', '.') }}% vs período anterior
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Tickets</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($kpis['cantidad'], 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Prom. $ {{ number_format($kpis['promedio'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Stock valorizado</p>
            <p class="mt-1 text-2xl font-bold">$ {{ number_format($stock['venta'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">Costo $ {{ number_format($stock['costo'], 2, ',', '.') }} · {{ $stock['bajo_minimo'] }} bajo mínimo</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Pedidos sugeridos</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">$ {{ number_format($pedidos['resumen']['inversion_total'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-500">{{ $pedidos['resumen']['criticos'] }} críticos · {{ $pedidos['resumen']['urgentes'] }} urgentes</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3">
        @foreach ([
            ['informes.ventas', 'Ventas', 'KPIs, gráficos, día / medio / vendedor / sucursal / cliente', 'indigo'],
            ['informes.productos', 'Productos vendidos', 'Cantidad, costo, venta y margen por artículo', 'violet'],
            ['informes.rentabilidad', 'Rentabilidad', 'Margen bruto/neto, gastos de caja y top rentables', 'emerald'],
            ['informes.categorias', 'Por categoría', 'Distribución de ventas y participación %', 'sky'],
            ['informes.pedidos', '¿Qué debo pedir?', 'Cobertura, cantidad sugerida, inversión y ROI', 'amber'],
            ['informes.stock', 'Stock valorizado', 'Inventario a costo y venta, alertas de mínimo', 'slate'],
            ['informes.libro-iva', 'Libro IVA', 'Comprobantes autorizados + export CSV', 'rose'],
            ['informes.cuentas-corrientes', 'Cuentas corrientes', 'Saldos de clientes + export', 'teal'],
            ['informes.cajas', 'Cajas', 'Sesiones, totales y cierres del período', 'orange'],
        ] as [$route, $titulo, $desc, $color])
            <a href="{{ route($route) }}"
               class="group rounded-xl border border-slate-200 bg-white p-5 shadow-sm transition hover:border-indigo-300 hover:shadow-md">
                <h2 class="text-base font-semibold text-slate-800 group-hover:text-indigo-700">{{ $titulo }}</h2>
                <p class="mt-1 text-sm text-slate-500">{{ $desc }}</p>
                <span class="mt-3 inline-block text-xs font-semibold text-indigo-600">Abrir →</span>
            </a>
        @endforeach
    </div>
@endsection
