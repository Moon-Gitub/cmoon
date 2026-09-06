@extends('layouts.app')

@section('titulo', "Lotes: {$producto->nombre}")

@section('contenido')
    <div class="mb-4 flex items-center justify-between gap-3">
        <p class="text-sm text-slate-500">
            Código <span class="font-mono font-medium text-slate-700">{{ $producto->codigo }}</span>
        </p>
        <a href="{{ route('productos.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Productos</a>
    </div>

    <form method="POST" action="{{ route('productos.lotes.store', $producto) }}"
          class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
        @csrf
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Código lote *</label>
            <input type="text" name="codigo_lote" required value="{{ old('codigo_lote') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Serie</label>
            <input type="text" name="serie" value="{{ old('serie') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Depósito</label>
            <select name="deposito_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">—</option>
                @foreach ($depositos as $dep)
                    <option value="{{ $dep->id }}" @selected(old('deposito_id') == $dep->id)>{{ $dep->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Vencimiento</label>
            <input type="date" name="vencimiento" value="{{ old('vencimiento') }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cantidad *</label>
            <input type="number" step="0.001" name="cantidad" required value="{{ old('cantidad', 0) }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Guardar lote
            </button>
        </div>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Lote</th>
                    <th class="px-4 py-3">Serie</th>
                    <th class="px-4 py-3">Depósito</th>
                    <th class="px-4 py-3">Vencimiento</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Ajustar</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($lotes as $lote)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $lote->codigo_lote }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $lote->serie ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $lote->deposito?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">
                            {{ $lote->vencimiento?->format('d/m/Y') ?? '—' }}
                        </td>
                        <td class="px-4 py-3 text-right font-semibold">
                            {{ rtrim(rtrim(number_format((float) $lote->cantidad, 3, ',', '.'), '0'), ',') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <form method="POST" action="{{ route('productos.lotes.store', $producto) }}" class="inline-flex items-center gap-1">
                                @csrf
                                <input type="hidden" name="codigo_lote" value="{{ $lote->codigo_lote }}">
                                <input type="hidden" name="serie" value="{{ $lote->serie }}">
                                <input type="hidden" name="deposito_id" value="{{ $lote->deposito_id }}">
                                @if ($lote->vencimiento)
                                    <input type="hidden" name="vencimiento" value="{{ $lote->vencimiento->format('Y-m-d') }}">
                                @endif
                                <input type="number" step="0.001" name="cantidad" value="{{ $lote->cantidad }}"
                                       class="w-24 rounded border border-slate-300 px-2 py-1 text-xs text-right">
                                <button class="rounded bg-slate-700 px-2 py-1 text-xs text-white">OK</button>
                            </form>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin lotes registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
