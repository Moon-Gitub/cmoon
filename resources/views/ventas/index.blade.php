@extends('layouts.app')

@section('titulo', 'Ventas')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas</option>
                <option value="completada" {{ request('estado') === 'completada' ? 'selected' : '' }}>Completadas</option>
                <option value="anulada" {{ request('estado') === 'anulada' ? 'selected' : '' }}>Anuladas</option>
            </select>
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
        </form>

        <div class="flex items-center gap-3">
            <div class="rounded-lg bg-emerald-50 px-4 py-2 text-sm">
                <span class="text-emerald-600">Total del período:</span>
                <span class="font-bold text-emerald-700">$ {{ number_format((float) $totalPeriodo, 2, ',', '.') }}</span>
            </div>
            @can('pos.vender')
                <a href="{{ route('pos') }}"
                   class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Ir al POS
                </a>
            @endcan
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">N°</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Vendedor</th>
                    <th class="px-4 py-3">Pago</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($ventas as $v)
                    <tr class="hover:bg-slate-50 {{ $v->estado === 'anulada' ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-mono font-semibold">#{{ str_pad($v->numero, 6, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $v->fecha->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $v->cliente?->nombre ?? 'Consumidor final' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $v->vendedor->name }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">
                            {{ $v->pagos->map(fn ($p) => $p->medioPago->nombre)->unique()->implode(', ') }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($v->estado === 'completada')
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Completada</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Anulada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $v->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('ventas.show', $v) }}"
                                   class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Ver</a>
                                <a href="{{ route('ventas.ticket', $v) }}" target="_blank"
                                   class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Ticket</a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No hay ventas en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $ventas->links() }}</div>
@endsection
