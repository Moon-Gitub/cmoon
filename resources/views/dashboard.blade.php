@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="mb-6 rounded-xl border border-indigo-200 bg-indigo-50 p-4 text-sm text-indigo-900">
        <p class="font-semibold">Fase 1 completada: base del sistema</p>
        <p>Autenticación, roles y permisos, empresa y sucursales ya están operativos. Los módulos de venta se habilitan en las próximas fases.</p>
    </div>

    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        @php
            $tarjetas = [
                ['titulo' => 'Ventas de hoy', 'valor' => '—', 'detalle' => 'Disponible en fase 3'],
                ['titulo' => 'Caja actual', 'valor' => '—', 'detalle' => 'Disponible en fase 3'],
                ['titulo' => 'Productos activos', 'valor' => \App\Models\Producto::where('activo', true)->count(), 'detalle' => 'En el catálogo'],
                ['titulo' => 'Usuarios', 'valor' => \App\Models\User::count(), 'detalle' => 'Activos en el sistema'],
            ];
        @endphp

        @foreach ($tarjetas as $tarjeta)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <p class="text-sm text-slate-500">{{ $tarjeta['titulo'] }}</p>
                <p class="mt-1 text-3xl font-bold text-slate-900">{{ $tarjeta['valor'] }}</p>
                <p class="mt-1 text-xs text-slate-400">{{ $tarjeta['detalle'] }}</p>
            </div>
        @endforeach
    </div>
@endsection
