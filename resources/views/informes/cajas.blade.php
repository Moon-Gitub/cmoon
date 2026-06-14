@extends('layouts.app')

@section('titulo', 'Informe de cajas')

@section('contenido')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Desde</label>
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ventas en caja (período)</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($totales['ventas'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Sesiones de caja</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($totales['sesiones'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Caja</th>
                    <th class="px-4 py-3">Cajero</th>
                    <th class="px-4 py-3">Apertura</th>
                    <th class="px-4 py-3">Cierre</th>
                    <th class="px-4 py-3 text-right">Ventas</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sesiones as $sesion)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $sesion->caja->nombre }}</td>
                        <td class="px-4 py-3">{{ $sesion->usuario->name }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $sesion->abierta_at->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3 whitespace-nowrap">{{ $sesion->cerrada_at?->format('d/m/Y H:i') ?? '—' }}</td>
                        <td class="px-4 py-3 text-right">{{ (int) $sesion->ventas_count }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) ($sesion->ventas_total ?? 0), 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($sesion->estado === 'abierta')
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Abierta</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Cerrada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No hay sesiones en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $sesiones->links() }}</div>
@endsection
