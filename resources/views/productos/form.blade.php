@extends('layouts.app')

@php($esNuevo = ! $producto->exists)

@section('titulo', $esNuevo ? 'Nuevo producto' : "Editar: {$producto->nombre}")

@section('contenido')
    <form method="POST"
          action="{{ $esNuevo ? route('productos.store') : route('productos.update', $producto) }}"
          class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @unless($esNuevo) @method('PUT') @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Código / código de barras *</label>
                <input type="text" name="codigo" value="{{ old('codigo', $producto->codigo) }}" required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('codigo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $producto->nombre) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Descripción</label>
            <textarea name="descripcion" rows="2"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('descripcion', $producto->descripcion) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Categoría</label>
                <select name="categoria_id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">Sin categoría</option>
                    @foreach ($categorias as $cat)
                        <option value="{{ $cat->id }}"
                            {{ (string) old('categoria_id', $producto->categoria_id) === (string) $cat->id ? 'selected' : '' }}>
                            {{ $cat->nombre }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Unidad *</label>
                <select name="unidad" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @foreach (['UN' => 'Unidad', 'KG' => 'Kilogramo', 'LT' => 'Litro', 'MT' => 'Metro'] as $valor => $texto)
                        <option value="{{ $valor }}" {{ old('unidad', $producto->unidad) === $valor ? 'selected' : '' }}>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="pesable" value="1"
                           {{ old('pesable', $producto->pesable) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Producto de balanza
                </label>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Precio compra *</label>
                <input type="number" step="0.01" min="0" name="precio_compra"
                       value="{{ old('precio_compra', $producto->precio_compra) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('precio_compra')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Precio venta *</label>
                <input type="number" step="0.01" min="0" name="precio_venta"
                       value="{{ old('precio_venta', $producto->precio_venta) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('precio_venta')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Alícuota IVA *</label>
                <select name="alicuota_iva" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @foreach (['21' => '21%', '10.5' => '10,5%', '27' => '27%', '0' => '0% (exento)'] as $valor => $texto)
                        <option value="{{ $valor }}"
                            {{ rtrim(rtrim((string) old('alicuota_iva', $producto->alicuota_iva), '0'), '.') === rtrim(rtrim($valor, '0'), '.') ? 'selected' : '' }}>
                            {{ $texto }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Stock mínimo</label>
                <input type="number" step="0.001" min="0" name="stock_minimo"
                       value="{{ old('stock_minimo', $producto->stock_minimo) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="activo" value="1"
                   {{ old('activo', $esNuevo ? true : $producto->activo) ? 'checked' : '' }}
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Producto activo (visible para la venta)
        </label>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $esNuevo ? 'Crear producto' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('productos.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
@endsection
