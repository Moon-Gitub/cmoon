@extends('layouts.app')

@section('titulo', 'Órdenes de compra')

@section('contenido')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado</label>
            <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach (['borrador', 'enviada', 'parcial', 'recibida', 'anulada'] as $est)
                    <option value="{{ $est }}" @selected($estado === $est)>{{ ucfirst($est) }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
        @can('ordenes-compra.gestionar')
            <a href="{{ route('ordenes-compra.create') }}"
               class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                + Nueva OC
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
                    <th class="px-4 py-3">Sucursal</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($ordenes as $orden)
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('ordenes-compra.show', $orden) }}'">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">#{{ $orden->numero }}</td>
                        <td class="px-4 py-3">{{ $orden->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $orden->proveedor->razon_social }}</td>
                        <td class="px-4 py-3">{{ $orden->sucursal->nombre }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $orden->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ $orden->estado }}</span>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No hay órdenes de compra.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $ordenes->links() }}</div>
@endsection
