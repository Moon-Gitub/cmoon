@extends('layouts.app')

@section('titulo', 'Remito desde presupuesto')

@section('contenido')
    <form method="POST" action="{{ route('remitos.store') }}" class="max-w-xl space-y-4">
        @csrf
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <label class="mb-1 block text-sm font-medium text-slate-700">Presupuesto *</label>
            <select name="presupuesto_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Elegí un presupuesto…</option>
                @foreach ($presupuestos as $p)
                    <option value="{{ $p->id }}" @selected(old('presupuesto_id', request('presupuesto_id')) == $p->id)>
                        #{{ $p->numero }} — {{ $p->cliente?->nombre ?? 'Sin cliente' }}
                        — $ {{ number_format((float) $p->total, 2, ',', '.') }}
                        ({{ $p->fecha?->format('d/m/Y') }}) [{{ $p->estado }}]
                    </option>
                @endforeach
            </select>
            @error('presupuesto_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            @if ($presupuestos->isEmpty())
                <p class="mt-2 text-sm text-amber-700">No hay presupuestos disponibles para remito.</p>
            @endif
        </div>

        <div class="flex gap-3">
            <a href="{{ route('remitos.index') }}" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-medium hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700 disabled:opacity-50"
                    @if ($presupuestos->isEmpty()) disabled @endif>
                Emitir remito
            </button>
        </div>
    </form>
@endsection
