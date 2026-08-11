@extends('layouts.app')

@section('titulo', 'Cambio de precio masivo')

@section('contenido')
    <form method="POST" action="{{ route('productos.precio-masivo.aplicar') }}"
          class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
          onsubmit="return confirm('¿Aplicar el cambio de precio a los productos filtrados?')">
        @csrf

        <p class="text-sm text-slate-600">
            Ajustá precios de venta o compra por categoría (o todos los activos).
            Por proveedor: aún no hay <code>proveedor_id</code> en productos; usar categoría o import CSV.
        </p>

        <div>
            <label class="mb-1 block text-sm font-medium">Categoría</label>
            <select name="categoria_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas las categorías (productos activos)</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id }}">{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Campo</label>
                <select name="campo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="precio_venta">Precio de venta</option>
                    <option value="precio_compra">Precio de compra</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Modo</label>
                <select name="modo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="porcentaje">Porcentaje (%)</option>
                    <option value="fijo">Monto fijo ($)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Valor</label>
                <input type="number" step="0.01" name="valor" required
                       placeholder="ej. 10 o -5"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="flex gap-3">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
            <a href="{{ route('productos.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
        </div>
    </form>
@endsection
