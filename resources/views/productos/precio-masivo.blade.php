@extends('layouts.app')

@section('titulo', 'Cambio de precio masivo')

@section('contenido')
    <form method="POST" action="{{ route('productos.precio-masivo.aplicar') }}"
          x-data="{ campo: '{{ old('campo', 'precio_venta') }}', baseIva: '{{ old('base_iva', 'con_iva') }}' }"
          class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm"
          onsubmit="return confirm('¿Aplicar el cambio de precio a los productos filtrados?')">
        @csrf

        <p class="text-sm text-slate-600">
            Ajustá precios de venta o compra por categoría (o todos los activos).
            El <strong>precio de venta</strong> en el sistema siempre se guarda <strong>con IVA incluido</strong>.
        </p>

        <div>
            <label class="mb-1 block text-sm font-medium">Categoría</label>
            <select name="categoria_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas las categorías (productos activos)</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id }}" @selected(old('categoria_id') == $cat->id)>{{ $cat->nombre }}</option>
                @endforeach
            </select>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium">Campo</label>
                <select name="campo" x-model="campo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="precio_venta">Precio de venta</option>
                    <option value="precio_compra">Precio de compra</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Modo</label>
                <select name="modo" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="porcentaje" @selected(old('modo', 'porcentaje') === 'porcentaje')>Porcentaje (%)</option>
                    <option value="fijo" @selected(old('modo') === 'fijo')>Monto fijo ($)</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Valor</label>
                <input type="number" step="0.01" name="valor" required value="{{ old('valor') }}"
                       placeholder="ej. 10 o -5"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('valor')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div x-show="campo === 'precio_venta'" x-cloak>
            <label class="mb-1 block text-sm font-medium">¿Con IVA o sin IVA?</label>
            <select name="base_iva" x-model="baseIva" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="con_iva">Aplicar sobre el precio con IVA incluido (público)</option>
                <option value="sin_iva">Aplicar sobre el neto (sin IVA) y volver a sumar el IVA del producto</option>
            </select>
            <p class="mt-1 text-xs text-slate-500" x-show="baseIva === 'con_iva'">
                Ejemplo: $1210 con 21% → +10% → $1331 (todo sobre el precio de góndola).
            </p>
            <p class="mt-1 text-xs text-slate-500" x-show="baseIva === 'sin_iva'">
                Ejemplo: $1210 con 21% → neto $1000 → +10% → $1100 → se guarda $1331 (con IVA).
                Usa la alícuota de cada producto (0 / 10,5 / 21 / 27).
            </p>
        </div>

        <div class="flex gap-3">
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
            <a href="{{ route('productos.index') }}" class="rounded-lg border border-slate-300 px-4 py-2 text-sm hover:bg-slate-50">Cancelar</a>
        </div>
    </form>
@endsection
