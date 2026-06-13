@extends('layouts.app')

@section('titulo', 'Comprobantes')

@section('contenido')
    @unless ($hayEmisores)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-800">
            Todavía no hay emisores AFIP configurados.
            @can('emisores.ver')
                <a href="{{ route('emisores.index') }}" class="font-semibold underline">Configurar emisores →</a>
            @endcan
        </div>
    @endunless

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
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado</label>
            <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach (['autorizado' => 'Autorizado', 'rechazado' => 'Rechazado', 'error' => 'Error', 'pendiente' => 'Pendiente'] as $valor => $nombre)
                    <option value="{{ $valor }}" {{ request('estado') === $valor ? 'selected' : '' }}>{{ $nombre }}</option>
                @endforeach
            </select>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Comprobante</th>
                    <th class="px-4 py-3">Receptor</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">CAE</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($comprobantes as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $c->fecha_emision->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <p class="font-medium">{{ $c->tipoNombre() }}</p>
                            <p class="font-mono text-xs text-slate-400">{{ $c->numero ? $c->numeroFormateado() : 'Sin número' }}</p>
                        </td>
                        <td class="px-4 py-3">{{ $c->receptor_nombre }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $c->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $c->cae ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @switch($c->estado)
                                @case('autorizado')
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Autorizado</span>
                                    @break
                                @case('pendiente')
                                    <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Pendiente</span>
                                    @break
                                @default
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600"
                                          title="{{ $c->mensaje_afip }}">{{ ucfirst($c->estado) }}</span>
                            @endswitch
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($c->estado === 'autorizado')
                                <a href="{{ route('facturacion.show', $c) }}" target="_blank"
                                   class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Ver factura</a>
                            @elseif (in_array($c->estado, ['error', 'rechazado']))
                                @can('facturacion.emitir')
                                    <form method="POST" action="{{ route('facturacion.reintentar', $c) }}" class="inline">
                                        @csrf
                                        <button class="text-sm font-medium text-amber-600 hover:text-amber-800">Reintentar</button>
                                    </form>
                                @endcan
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No hay comprobantes en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $comprobantes->links() }}</div>
@endsection
