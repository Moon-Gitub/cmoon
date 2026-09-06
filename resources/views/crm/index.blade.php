@extends('layouts.app')

@section('titulo', 'CRM — Oportunidades')

@section('contenido')
    <form method="POST" action="{{ route('crm.store') }}"
          class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-6">
        @csrf
        <div class="lg:col-span-2">
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Título *</label>
            <input type="text" name="titulo" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cliente</label>
            <select name="cliente_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">—</option>
                @foreach ($clientes as $c)
                    <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Monto</label>
            <input type="number" step="0.01" min="0" name="monto" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Etapa</label>
            <select name="etapa" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @foreach ($etapas as $key => $label)
                    <option value="{{ $key }}">{{ $label }}</option>
                @endforeach
            </select>
        </div>
        <div class="flex items-end">
            <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Crear</button>
        </div>
    </form>

    <div class="grid grid-cols-1 gap-4 md:grid-cols-2 xl:grid-cols-3 2xl:grid-cols-6">
        @foreach ($porEtapa as $etapaKey => $items)
            <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <h2 class="border-b border-slate-100 px-3 py-2 text-xs font-semibold uppercase tracking-wider text-slate-500">
                    {{ $etapas[$etapaKey] }}
                    <span class="ml-1 text-slate-400">({{ $items->count() }})</span>
                </h2>
                <div class="max-h-[32rem] space-y-2 overflow-y-auto p-3">
                    @forelse ($items as $op)
                        <div class="rounded-lg border border-slate-100 bg-slate-50 p-3 text-sm">
                            <p class="font-semibold text-slate-800">{{ $op->titulo }}</p>
                            <p class="text-xs text-slate-500">{{ $op->cliente?->nombre ?? 'Sin cliente' }}</p>
                            @if ($op->monto)
                                <p class="mt-1 font-medium text-indigo-600">$ {{ number_format((float) $op->monto, 2, ',', '.') }}</p>
                            @endif

                            <form method="POST" action="{{ route('crm.etapa', $op) }}" class="mt-2 flex gap-1">
                                @csrf
                                <select name="etapa" class="flex-1 rounded border border-slate-300 px-1 py-1 text-xs">
                                    @foreach ($etapas as $k => $lab)
                                        <option value="{{ $k }}" @selected($op->etapa === $k)>{{ $lab }}</option>
                                    @endforeach
                                </select>
                                <button class="rounded bg-slate-700 px-2 py-1 text-xs text-white">→</button>
                            </form>

                            <form method="POST" action="{{ route('crm.actividad', $op) }}" class="mt-2 space-y-1">
                                @csrf
                                <input type="text" name="titulo" required placeholder="Nueva actividad…"
                                       class="w-full rounded border border-slate-300 px-2 py-1 text-xs">
                                <button class="text-xs text-indigo-600 hover:underline">+ Actividad</button>
                            </form>
                        </div>
                    @empty
                        <p class="py-6 text-center text-xs text-slate-400">Vacío</p>
                    @endforelse
                </div>
            </div>
        @endforeach
    </div>
@endsection
