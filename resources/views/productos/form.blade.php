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
                <div class="mb-1 flex items-center justify-between">
                    <label class="block text-sm font-medium text-slate-700">Nombre *</label>
                    <button type="button" id="btn-ia-producto"
                            class="text-xs font-medium text-indigo-600 hover:text-indigo-800">
                        Sugerir con IA (1 pregunta)
                    </button>
                </div>
                <input type="text" name="nombre" id="campo-nombre" value="{{ old('nombre', $producto->nombre) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                <p id="ia-producto-msg" class="mt-1 hidden text-xs text-slate-500"></p>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Descripción</label>
            <textarea name="descripcion" id="campo-descripcion" rows="2"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('descripcion', $producto->descripcion) }}</textarea>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Categoría</label>
                <select name="categoria_id" id="campo-categoria"
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
            <div class="flex items-end gap-4 pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="pesable" value="1"
                           {{ old('pesable', $producto->pesable) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Producto de balanza
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="es_combo" value="1"
                           {{ old('es_combo', $producto->es_combo) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Combo (producto compuesto)
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

        <fieldset class="rounded-lg border border-slate-200 p-4">
            <legend class="px-1 text-sm font-semibold text-slate-700">Publicar en canales</legend>
            <p class="mb-3 text-xs text-slate-500">Solo los tildados se sincronizan o se ofrecen en ese canal. El POS interno no cambia.</p>
            <div class="flex flex-wrap gap-4">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="publicar_shopify" value="1"
                           {{ old('publicar_shopify', $producto->publicar_shopify) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Shopify
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="publicar_whatsapp" value="1"
                           {{ old('publicar_whatsapp', $producto->publicar_whatsapp) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-emerald-600 focus:ring-emerald-500">
                    WhatsApp
                </label>
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="publicar_tiendanube" value="1"
                           {{ old('publicar_tiendanube', $producto->publicar_tiendanube) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-sky-600 focus:ring-sky-500">
                    Tiendanube
                </label>
            </div>
        </fieldset>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $esNuevo ? 'Crear producto' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('productos.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
    <script>
    document.getElementById('btn-ia-producto')?.addEventListener('click', async () => {
        const msg = document.getElementById('ia-producto-msg');
        msg.classList.remove('hidden');
        msg.textContent = 'Pensando…';
        try {
            const res = await fetch(@json(route('ia.productos.sugerir')), {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                    'Accept': 'application/json',
                    'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                },
                body: JSON.stringify({
                    codigo: document.querySelector('[name=codigo]')?.value,
                    nombre: document.getElementById('campo-nombre').value,
                    descripcion: document.getElementById('campo-descripcion').value,
                }),
            });
            const data = await res.json();
            if (!data.ok) {
                msg.textContent = data.texto || 'No se pudo sugerir.';
                return;
            }
            if (data.nombre) document.getElementById('campo-nombre').value = data.nombre;
            if (data.descripcion) document.getElementById('campo-descripcion').value = data.descripcion;
            if (data.categoria_id) document.getElementById('campo-categoria').value = data.categoria_id;
            msg.textContent = 'Revisá y guardá. Quedan ' + (data.cupo?.restantes ?? '—') + ' usos de IA este mes.'
                + (data.categoria && !data.categoria_id ? ' Categoría nueva sugerida: ' + data.categoria : '');
        } catch (e) {
            msg.textContent = 'Error de red.';
        }
    });
    </script>
@endsection
