@extends('layouts.app')

@section('titulo', 'Presupuestos')

@section('contenido')
    @if ($pendientesAprobacion > 0)
        <div class="mb-4 rounded-xl border border-orange-200 bg-orange-50 px-4 py-3 text-sm text-orange-800">
            Hay <strong>{{ $pendientesAprobacion }}</strong> pedido(s) del móvil esperando aprobación.
            <a href="{{ route('presupuestos.index', ['estado' => 'pendiente_aprobacion']) }}" class="font-semibold underline">Ver pendientes</a>
        </div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado</label>
            <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach ([
                    'pendiente_aprobacion' => 'Pendiente aprobación',
                    'aprobado' => 'Aprobado',
                    'pendiente' => 'Pendiente',
                    'convertido' => 'Convertido',
                    'rechazado' => 'Rechazado',
                    'anulado' => 'Anulado',
                ] as $valor => $nombre)
                    <option value="{{ $valor }}" {{ request('estado') === $valor ? 'selected' : '' }}>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
        @can('presupuestos.gestionar')
            <a href="{{ route('presupuestos.create') }}"
               class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                + Nuevo presupuesto
            </a>
        @endcan
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">#</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Vendedor</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">Estado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($presupuestos as $presupuesto)
                    <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('presupuestos.show', $presupuesto) }}'">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">#{{ str_pad($presupuesto->numero, 6, '0', STR_PAD_LEFT) }}</td>
                        <td class="px-4 py-3">{{ $presupuesto->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 font-medium">{{ $presupuesto->cliente?->nombre ?? 'Consumidor final' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $presupuesto->usuario->name }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $presupuesto->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @include('presupuestos.partials.estado-badge', ['presupuesto' => $presupuesto])
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No hay presupuestos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $presupuestos->links() }}</div>
@endsection
