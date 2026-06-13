@extends('layouts.app')

@section('titulo', 'Inicio')

@section('contenido')
    <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Ventas de hoy</p>
            <p class="mt-1 text-3xl font-bold text-indigo-600">$ {{ number_format($ventasHoyTotal, 2, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">{{ $ventasHoyCantidad }} operaciones</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Mi caja</p>
            @if ($sesionAbierta)
                <p class="mt-1 text-3xl font-bold text-emerald-600">{{ $sesionAbierta->caja->nombre }}</p>
                <p class="mt-1 text-xs text-slate-400">Abierta desde {{ $sesionAbierta->abierta_at->format('H:i') }}</p>
            @else
                <p class="mt-1 text-3xl font-bold text-slate-400">Cerrada</p>
                <p class="mt-1 text-xs text-slate-400">
                    @can('cajas.ver')<a href="{{ route('cajas.index') }}" class="text-indigo-600 hover:underline">Abrir caja →</a>@endcan
                </p>
            @endif
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Productos activos</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ number_format($productosActivos, 0, ',', '.') }}</p>
            <p class="mt-1 text-xs text-slate-400">En el catálogo</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">Usuarios activos</p>
            <p class="mt-1 text-3xl font-bold text-slate-900">{{ $usuariosActivos }}</p>
            <p class="mt-1 text-xs text-slate-400">Con acceso al sistema</p>
        </div>
    </div>

    @can('pos.vender')
        <a href="{{ route('pos') }}"
           class="mt-6 flex items-center justify-between rounded-xl bg-indigo-600 p-6 text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700">
            <div>
                <p class="text-xl font-bold">Abrir punto de venta</p>
                <p class="text-sm text-indigo-200">Pantalla de venta rápida con lector de código de barras · Funciona sin internet</p>
            </div>
            <svg class="h-10 w-10" fill="none" stroke="currentColor" stroke-width="1.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5L21 12m0 0l-7.5 7.5M21 12H3"/></svg>
        </a>
    @endcan

    <div class="mt-6 grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Últimos 7 días</h2>
            @php $maximo = max(1, (float) collect($ultimos7)->max()); @endphp
            <div class="space-y-2 p-5">
                @for ($i = 6; $i >= 0; $i--)
                    @php
                        $dia = now()->subDays($i);
                        $total = (float) ($ultimos7[$dia->toDateString()] ?? 0);
                    @endphp
                    <div class="flex items-center gap-3 text-sm">
                        <span class="w-16 text-slate-500">{{ $dia->isToday() ? 'Hoy' : $dia->translatedFormat('D d/m') }}</span>
                        <div class="h-5 flex-1 overflow-hidden rounded bg-slate-100">
                            <div class="h-full rounded bg-indigo-500" style="width: {{ round($total / $maximo * 100) }}%"></div>
                        </div>
                        <span class="w-28 text-right font-semibold">$ {{ number_format($total, 2, ',', '.') }}</span>
                    </div>
                @endfor
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Últimas ventas</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($ultimasVentas as $venta)
                        <tr class="hover:bg-slate-50">
                            <td class="px-5 py-2.5">
                                @can('ventas.ver')
                                    <a href="{{ route('ventas.show', $venta) }}" class="font-mono text-xs text-indigo-600 hover:underline">
                                        #{{ str_pad($venta->numero, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                @else
                                    <span class="font-mono text-xs">#{{ str_pad($venta->numero, 6, '0', STR_PAD_LEFT) }}</span>
                                @endcan
                            </td>
                            <td class="px-2 py-2.5">{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</td>
                            <td class="px-2 py-2.5 text-xs text-slate-400">{{ $venta->fecha->format('d/m H:i') }}</td>
                            <td class="px-5 py-2.5 text-right font-semibold {{ $venta->estado === 'anulada' ? 'text-red-400 line-through' : '' }}">
                                $ {{ number_format((float) $venta->total, 2, ',', '.') }}
                            </td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Todavía no hay ventas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
