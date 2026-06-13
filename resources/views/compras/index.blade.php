@extends('layouts.app')

@section('titulo', 'Compras')

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
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
        <p class="text-sm text-slate-500">Total: <span class="font-bold text-indigo-600">$ {{ number_format((float) $totalPeriodo, 2, ',', '.') }}</span></p>
        @can('compras.gestionar')
            <a href="{{ route('compras.create') }}"
               class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                + Nueva compra
            </a>
        @endcan
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Factura</th>
                    <th class="px-4 py-3">Condición</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($compras as $compra)
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('compras.show', $compra) }}'">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">#{{ $compra->id }}</td>
                        <td class="px-4 py-3">{{ $compra->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $compra->proveedor->razon_social }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $compra->factura_numero ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $compra->condicion === 'cuenta_corriente' ? 'Cta. corriente' : 'Contado' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $compra->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($compra->estado === 'completada')
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Completada</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Anulada</span>
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No hay compras en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $compras->links() }}</div>
@endsection
