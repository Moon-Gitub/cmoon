@extends('layouts.app')

@section('titulo', 'Nuevo cheque')

@section('contenido')
    <form method="POST" action="{{ route('cheques.store') }}" class="max-w-3xl space-y-4">
        @csrf
        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Número *</label>
                <input type="text" name="numero" value="{{ old('numero') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tipo *</label>
                <select name="tipo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="recibido">Recibido</option>
                    <option value="emitido">Emitido</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Banco</label>
                <input type="text" name="banco" value="{{ old('banco') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Plaza</label>
                <input type="text" name="plaza" value="{{ old('plaza') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Emisión *</label>
                <input type="date" name="fecha_emision" value="{{ old('fecha_emision', now()->format('Y-m-d')) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Vencimiento *</label>
                <input type="date" name="fecha_vencimiento" value="{{ old('fecha_vencimiento') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Importe *</label>
                <input type="number" step="0.01" min="0.01" name="importe" value="{{ old('importe') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Cliente (recibido)</label>
                <select name="cliente_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($clientes as $c)
                        <option value="{{ $c->id }}" @selected(old('cliente_id') == $c->id)>{{ $c->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor (emitido)</label>
                <select name="proveedor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($proveedores as $p)
                        <option value="{{ $p->id }}" @selected(old('proveedor_id') == $p->id)>{{ $p->razon_social }}</option>
                    @endforeach
                </select>
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <input type="text" name="observaciones" value="{{ old('observaciones') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex gap-3">
            <a href="{{ route('cheques.index') }}" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-medium hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Guardar cheque</button>
        </div>
    </form>
@endsection
