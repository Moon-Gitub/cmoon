@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    @php
        $ventasHoy = \App\Models\Venta::where('estado', 'completada')->whereDate('fecha', today());
        $cajasAbiertas = \App\Models\CajaSesion::where('estado', 'abierta')->count();
        $tarjetas = [
            ['titulo' => 'Ventas de hoy', 'valor' => '$ '.number_format((float) $ventasHoy->sum('total'), 2, ',', '.'), 'detalle' => $ventasHoy->count().' operaciones'],
            ['titulo' => 'Cajas abiertas', 'valor' => $cajasAbiertas, 'detalle' => $cajasAbiertas ? 'En operación' : 'Todas cerradas'],
            ['titulo' => 'Productos activos', 'valor' => \App\Models\Producto::where('activo', true)->count(), 'detalle' => 'En el catálogo'],
            ['titulo' => 'Clientes', 'valor' => \App\Models\Cliente::where('activo', true)->count(), 'detalle' => 'Activos'],
        ];
    @endphp

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @foreach ($tarjetas as $tarjeta)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ $tarjeta['titulo'] }}</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $tarjeta['valor'] }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $tarjeta['detalle'] }}</p>
            </div>
        @endforeach
    </div>

    @can('pos.vender')
        <a href="{{ route('pos') }}"
           class="mt-6 flex items-center justify-between rounded-xl bg-indigo-600 p-6 text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700">
            <div>
                <p class="text-xl font-bold">Abrir punto de venta</p>
                <p class="text-sm text-indigo-200">Pantalla de venta rápida con lector de código de barras</p>
            </div>
            <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </a>
    @endcan
@endsection
