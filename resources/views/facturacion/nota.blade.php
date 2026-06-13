@extends('layouts.app')

@section('titulo', 'Emitir nota de crédito / débito')

@section('contenido')
    <div class="max-w-xl space-y-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Comprobante original</h2>
            <dl class="space-y-1.5 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Comprobante</dt>
                    <dd class="font-medium">{{ $comprobante->tipoNombre() }} {{ $comprobante->numeroFormateado() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Receptor</dt>
                    <dd class="font-medium">{{ $comprobante->receptor_nombre }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt>
                    <dd class="font-medium">{{ $comprobante->fecha_emision->format('d/m/Y') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Total</dt>
                    <dd class="font-bold text-indigo-600">$ {{ number_format((float) $comprobante->total, 2, ',', '.') }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">CAE</dt>
                    <dd class="font-mono text-xs">{{ $comprobante->cae }}</dd></div>
            </dl>
        </div>

        <form method="POST" action="{{ route('facturacion.nota.store', $comprobante) }}"
              class="space-y-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de nota *</label>
                <select name="clase" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="credito">Nota de crédito (anula o devuelve importe)</option>
                    <option value="debito">Nota de débito (suma importe)</option>
                </select>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Importe</label>
                <input type="number" name="importe" step="0.01" min="0.01" max="{{ $comprobante->total }}"
                       value="{{ old('importe', $comprobante->total) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <p class="mt-1 text-xs text-slate-400">Dejá el total para anular el comprobante completo, o poné un importe parcial.</p>
                @error('importe')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Concepto *</label>
                <input type="text" name="concepto" value="{{ old('concepto') }}" required
                       placeholder="Ej: Devolución de mercadería"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('concepto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            @error('comprobante')<p class="text-sm text-red-600">{{ $message }}</p>@enderror

            <div class="flex gap-3">
                <a href="{{ route('facturacion.index') }}"
                   class="flex-1 rounded-xl border border-slate-300 py-2.5 text-center text-sm font-medium hover:bg-slate-50">Cancelar</a>
                <button class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                    Emitir y solicitar CAE
                </button>
            </div>
        </form>
    </div>
@endsection
