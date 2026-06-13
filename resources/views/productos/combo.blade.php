@extends('layouts.app')

@section('titulo', 'Combo: '.$producto->nombre)

@section('contenido')
    <div class="max-w-2xl space-y-4">
        <div class="rounded-xl border border-purple-200 bg-purple-50 p-4 text-sm text-purple-800">
            Al vender <strong>{{ $producto->nombre }}</strong>, el stock se descuenta de sus componentes
            (el combo en sí no maneja stock propio).
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Componentes</h2>
            <div class="space-y-1.5">
                @forelse ($producto->componentes as $componente)
                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2 text-sm">
                        <span>
                            <span class="font-semibold">{{ rtrim(rtrim(number_format((float) $componente->cantidad, 3, ',', '.'), '0'), ',') }} ×</span>
                            {{ $componente->componente?->nombre ?? 'Producto eliminado' }}
                            <span class="font-mono text-xs text-slate-400">{{ $componente->componente?->codigo }}</span>
                        </span>
                        <form method="POST" action="{{ route('productos.combo.quitar', [$producto, $componente]) }}"
                              onsubmit="return confirm('¿Quitar este componente?')">
                            @csrf @method('DELETE')
                            <button class="text-xs text-red-500 hover:text-red-700">Quitar</button>
                        </form>
                    </div>
                @empty
                    <p class="py-4 text-center text-sm text-slate-400">El combo todavía no tiene componentes.</p>
                @endforelse
            </div>

            <form method="POST" action="{{ route('productos.combo.agregar', $producto) }}" class="mt-4 flex gap-2 border-t border-slate-100 pt-4">
                @csrf
                <select name="componente_id" required class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Agregar producto…</option>
                    @foreach ($candidatos as $candidato)
                        <option value="{{ $candidato->id }}">{{ $candidato->nombre }} ({{ $candidato->codigo }})</option>
                    @endforeach
                </select>
                <input type="number" name="cantidad" value="1" step="any" min="0.001" required
                       class="w-24 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Agregar
                </button>
            </form>
            @error('componente_id')<p class="mt-2 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <a href="{{ route('productos.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a productos</a>
    </div>
@endsection
