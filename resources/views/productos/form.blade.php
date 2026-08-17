@extends('layouts.app')

@php
    $esNuevo = ! $producto->exists;
    $margenInicial = old('margen_ganancia', $producto->margen_ganancia ?? ($esNuevo ? 40 : 0));
    $usarPorcentaje = old('utilizar_porcentaje', (float) $margenInicial > 0);
    $monedaInicial = old('precio_compra_moneda', ((float) old('precio_compra_dolar', $producto->precio_compra_dolar ?? 0) > 0) ? 'dolar' : 'peso');
    $cotizacion = (float) ($cotizacionDolar ?? 0);
@endphp

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

        <div class="space-y-4 rounded-lg border border-slate-200 p-4"
             x-data="precioProducto({
                compra: {{ json_encode((float) old('precio_compra', $producto->precio_compra ?? 0)) }},
                compraDolar: {{ json_encode((float) old('precio_compra_dolar', $producto->precio_compra_dolar ?? 0)) }},
                moneda: {{ json_encode($monedaInicial) }},
                usarPorcentaje: {{ $usarPorcentaje ? 'true' : 'false' }},
                margen: {{ json_encode((float) $margenInicial) }},
                iva: {{ json_encode((float) old('alicuota_iva', $producto->alicuota_iva ?? 21)) }},
                venta: {{ json_encode((float) old('precio_venta', $producto->precio_venta ?? 0)) }},
                cotizacion: {{ json_encode($cotizacion) }},
             })">

            <div class="border-b border-slate-200 pb-1 text-sm font-semibold text-slate-700">Compra</div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div class="space-y-3">
                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">$</span>
                            <input type="number" step="0.01" min="0" name="precio_compra" required
                                   x-model.number="compra"
                                   @input="onCompraPesos()"
                                   :readonly="moneda === 'dolar'"
                                   placeholder="Precio compra"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-7 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-slate-50">
                        </div>
                        <input type="radio" name="precio_compra_moneda" value="peso"
                               x-model="moneda" @change="onMonedaChange()"
                               class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    </div>

                    <div class="flex items-center gap-2">
                        <div class="relative flex-1">
                            <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">U$S</span>
                            <input type="number" step="0.01" min="0" name="precio_compra_dolar"
                                   x-model.number="compraDolar"
                                   @input="onCompraDolar()"
                                   :readonly="moneda !== 'dolar'"
                                   placeholder="Precio compra dólar"
                                   class="w-full rounded-lg border border-slate-300 py-2 pl-10 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200 disabled:bg-slate-50">
                        </div>
                        <input type="radio" name="precio_compra_moneda" value="dolar"
                               x-model="moneda" @change="onMonedaChange()"
                               class="h-4 w-4 border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    </div>
                    <p class="text-xs text-slate-500" x-show="cotizacion > 0">
                        Cotización: $ <span x-text="cotizacion.toFixed(2)"></span>
                        <a href="{{ route('empresa.edit') }}" class="ml-1 text-indigo-600 hover:underline">cambiar</a>
                    </p>
                    <p class="text-xs text-amber-600" x-show="moneda === 'dolar' && cotizacion <= 0">
                        Configurá la cotización del dólar en Datos de la empresa para convertir U$S a $.
                    </p>
                </div>

                <div class="flex flex-wrap items-start gap-3 sm:justify-end">
                    <label class="flex items-center gap-2 pt-2 text-sm text-slate-700">
                        <input type="checkbox" name="utilizar_porcentaje" value="1"
                               x-model="usarPorcentaje" @change="onPorcentajeToggle()"
                               class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                        Utilizar porcentaje
                    </label>
                    <div class="flex items-center gap-1">
                        <input type="number" step="0.01" min="0" name="margen_ganancia"
                               x-model.number="margen" @input="recalcular()"
                               :readonly="!usarPorcentaje"
                               class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        <span class="text-sm text-slate-600">%</span>
                    </div>
                </div>
            </div>

            <div class="border-b border-slate-200 pb-1 pt-2 text-sm font-semibold text-slate-700">Venta</div>

            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">% IVA</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">%</span>
                        <select name="alicuota_iva" required x-model.number="iva" @change="recalcular()"
                                class="w-full rounded-lg border border-slate-300 py-2 pl-7 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            @foreach (['21' => '21%', '10.5' => '10,5%', '27' => '27%', '0' => '0% (exento)'] as $valor => $texto)
                                <option value="{{ $valor }}">{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">$ Venta</label>
                    <div class="relative">
                        <span class="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-slate-500">$</span>
                        <input type="number" step="0.01" min="0" name="precio_venta" required
                               x-model.number="venta"
                               :readonly="usarPorcentaje"
                               placeholder="$ publi"
                               class="w-full rounded-lg border border-slate-300 py-2 pl-7 pr-3 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    </div>
                    <p class="mt-1 text-xs text-slate-500">Precio público con IVA incluido.</p>
                    @error('precio_venta')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    @error('precio_compra')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Stock mínimo</label>
                <input type="number" step="0.001" min="0" name="stock_minimo"
                       value="{{ old('stock_minimo', $producto->stock_minimo) }}"
                       class="w-full max-w-xs rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
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
    function precioProducto(init) {
        const floor2 = (n) => Math.floor((Number(n) || 0) * 100) / 100;
        return {
            compra: Number(init.compra) || 0,
            compraDolar: Number(init.compraDolar) || 0,
            moneda: init.moneda || 'peso',
            usarPorcentaje: !!init.usarPorcentaje,
            margen: Number(init.margen) || 0,
            iva: Number(init.iva) || 21,
            venta: Number(init.venta) || 0,
            cotizacion: Number(init.cotizacion) || 0,
            onMonedaChange() {
                if (this.moneda === 'dolar') {
                    this.onCompraDolar();
                }
            },
            onCompraPesos() {
                this.recalcular();
            },
            onCompraDolar() {
                if (this.moneda === 'dolar' && this.cotizacion > 0) {
                    this.compra = floor2(this.compraDolar * this.cotizacion);
                }
                this.recalcular();
            },
            onPorcentajeToggle() {
                if (this.usarPorcentaje) {
                    if (!this.margen) this.margen = 40;
                } else {
                    this.margen = 0;
                }
                this.recalcular();
            },
            recalcular() {
                if (!this.usarPorcentaje) return;
                const neto = floor2(this.compra + this.compra * (Number(this.margen) || 0) / 100);
                this.venta = floor2(neto + neto * (Number(this.iva) || 0) / 100);
            },
        };
    }

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
