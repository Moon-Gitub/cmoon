@extends('layouts.app')

@section('titulo', 'Agenda de cobranza')

@section('contenido')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Vencen hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
        <p class="text-sm text-slate-500">{{ $agenda->count() }} clientes con saldo</p>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3 text-right">Saldo</th>
                    <th class="px-4 py-3">Próx. vencimiento</th>
                    <th class="px-4 py-3 text-right">Días atraso</th>
                    <th class="px-4 py-3">Último movimiento</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($agenda as $row)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">
                            <a href="{{ route('clientes.cuenta', $row->cliente) }}" class="text-indigo-600 hover:underline">
                                {{ $row->nombre }}
                            </a>
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $row->saldo, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($row->proximo_vencimiento)
                                {{ $row->proximo_vencimiento->format('d/m/Y') }}
                            @else
                                <span class="text-slate-400">—</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @if ($row->dias_atraso > 0)
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">{{ $row->dias_atraso }} d</span>
                            @else
                                <span class="text-slate-400">0</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-slate-500">
                            @if ($row->ultimo_movimiento)
                                {{ $row->ultimo_movimiento->fecha?->format('d/m/Y') }} · {{ $row->ultimo_movimiento->concepto }}
                            @else
                                —
                            @endif
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No hay clientes con deuda en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
