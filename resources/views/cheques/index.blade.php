@extends('layouts.app')

@section('titulo', 'Cheques')

@section('contenido')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Estado</label>
            <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                @foreach (['en_cartera' => 'En cartera', 'depositado' => 'Depositado', 'cobrado' => 'Cobrado', 'rechazado' => 'Rechazado', 'anulado' => 'Anulado'] as $val => $label)
                    <option value="{{ $val }}" @selected($estado === $val)>{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Tipo</label>
            <select name="tipo" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos</option>
                <option value="recibido" @selected($tipo === 'recibido')>Recibido</option>
                <option value="emitido" @selected($tipo === 'emitido')>Emitido</option>
            </select>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
        <a href="{{ route('cheques.create') }}"
           class="ml-auto rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
            + Nuevo cheque
        </a>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">N°</th>
                    <th class="px-4 py-3">Banco</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Titular</th>
                    <th class="px-4 py-3">Vencimiento</th>
                    <th class="px-4 py-3 text-right">Importe</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Cambiar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($cheques as $cheque)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs">{{ $cheque->numero }}</td>
                        <td class="px-4 py-3">{{ $cheque->banco ?? '—' }}</td>
                        <td class="px-4 py-3">{{ $cheque->tipo === 'emitido' ? 'Emitido' : 'Recibido' }}</td>
                        <td class="px-4 py-3">
                            {{ $cheque->cliente?->nombre ?? $cheque->proveedor?->razon_social ?? '—' }}
                        </td>
                        <td class="px-4 py-3">{{ $cheque->fecha_vencimiento->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $cheque->importe, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-700">{{ str_replace('_', ' ', $cheque->estado) }}</span>
                        </td>
                        <td class="px-4 py-3">
                            <form method="POST" action="{{ route('cheques.cambiar-estado', $cheque) }}" class="flex gap-1">
                                @csrf
                                <select name="estado" class="rounded border border-slate-300 px-1 py-1 text-xs">
                                    @foreach (['en_cartera', 'depositado', 'cobrado', 'rechazado', 'anulado'] as $est)
                                        <option value="{{ $est }}" @selected($cheque->estado === $est)>{{ $est }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded bg-slate-700 px-2 py-1 text-xs text-white">OK</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">No hay cheques.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $cheques->links() }}</div>
@endsection
