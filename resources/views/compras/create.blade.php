@extends('layouts.app')

@section('titulo', 'Nueva compra')

@section('contenido')
    <form method="POST" action="{{ route('compras.store') }}"
          x-data="compraForm(@js(route('busqueda.productos')))"
          class="max-w-5xl space-y-4">
        @csrf

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <x-buscador
                    :url="route('busqueda.proveedores')"
                    name="proveedor_id"
                    label="Proveedor"
                    placeholder="Razón social o CUIT…"
                    :value="old('proveedor_id')"
                    :required="true"
                />
                @error('proveedor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sucursal destino *</label>
                <select name="sucursal_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($sucursales as $sucursal)
                        <option value="{{ $sucursal->id }}">{{ $sucursal->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Fecha *</label>
                <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Factura del proveedor</label>
                <input type="text" name="factura_numero" value="{{ old('factura_numero') }}" placeholder="0001-00001234"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Condición *</label>
                <select name="condicion" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="contado">Contado</option>
                    <option value="cuenta_corriente">Cuenta corriente</option>
                </select>
            </div>
            <div class="lg:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <input type="text" name="observaciones" value="{{ old('observaciones') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Ítems</h2>
            <div class="space-y-2">
                <template x-for="(item, idx) in lineas" :key="idx">
                    <div class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                        <div class="relative min-w-[14rem] flex-1">
                            <input type="hidden" :name="'items['+idx+'][producto_id]'" :value="item.producto_id">
                            <input type="text" x-model="item.q"
                                   @input="buscarProducto(item)"
                                   @keydown.arrow-down.prevent="mover(item, 1)"
                                   @keydown.arrow-up.prevent="mover(item, -1)"
                                   @keydown.enter.prevent="confirmarProducto(item)"
                                   @keydown.escape="item.abierto = false"
                                   placeholder="Código o producto…"
                                   autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <ul x-show="item.abierto && item.sugerencias.length" x-cloak
                                class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                                <template x-for="(sug, i) in item.sugerencias" :key="sug.id">
                                    <li>
                                        <button type="button" class="block w-full px-3 py-2 text-left hover:bg-indigo-50"
                                                :class="i === item.indice ? 'bg-indigo-50' : ''"
                                                @mousedown.prevent="elegirProducto(item, sug)">
                                            <span x-text="sug.label"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                            <p class="mt-0.5 text-[11px] text-slate-400">Buscá producto o escribí descripción libre abajo</p>
                        </div>
                        <input type="text" :name="'items['+idx+'][descripcion]'" x-model="item.descripcion"
                               placeholder="Descripción"
                               class="min-w-[10rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="number" :name="'items['+idx+'][cantidad]'" x-model.number="item.cantidad" required
                               step="any" min="0.001" placeholder="Cant."
                               class="w-24 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <input type="number" :name="'items['+idx+'][costo_unitario]'" x-model.number="item.costo" required
                               step="0.01" min="0" placeholder="Costo unit."
                               class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <span class="w-28 py-2 text-right text-sm font-semibold"
                              x-text="fmt((item.cantidad || 0) * (item.costo || 0))"></span>
                        <button type="button" @click="lineas.splice(idx, 1)" x-show="lineas.length > 1"
                                class="rounded-lg p-2 text-red-500 hover:bg-red-50">✕</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="agregarLinea()"
                    class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Agregar ítem</button>

            <div class="mt-4 flex items-center justify-between border-t border-slate-100 pt-3">
                <label class="flex items-center gap-2 text-sm text-slate-600">
                    <input type="checkbox" name="actualizar_costos" value="1" checked class="rounded border-slate-300">
                    Actualizar el precio de costo de los productos
                </label>
                <p class="text-lg font-bold">TOTAL <span class="text-indigo-600" x-text="fmt(total())"></span></p>
            </div>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc">
                    @foreach ($errors->all() as $error)<li>{{ $error }}</li>@endforeach
                </ul>
            </div>
        @endif

        <div class="flex gap-3">
            <a href="{{ route('compras.index') }}"
               class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-medium hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                Guardar compra
            </button>
        </div>
    </form>

    <script>
        function compraForm(urlProductos) {
            const lineaVacia = () => ({
                producto_id: '', descripcion: '', cantidad: 1, costo: null,
                q: '', sugerencias: [], abierto: false, indice: -1, _t: null,
            });
            return {
                urlProductos,
                lineas: [lineaVacia()],
                agregarLinea() { this.lineas.push(lineaVacia()); },
                buscarProducto(item) {
                    item.producto_id = '';
                    clearTimeout(item._t);
                    const term = (item.q || '').trim();
                    if (term.length < 2) { item.sugerencias = []; item.abierto = false; return; }
                    item._t = setTimeout(async () => {
                        try {
                            const res = await fetch(`${this.urlProductos}?q=${encodeURIComponent(term)}&sin_combos=1&limit=20`, {
                                headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                            });
                            item.sugerencias = res.ok ? await res.json() : [];
                            item.abierto = item.sugerencias.length > 0;
                            item.indice = item.abierto ? 0 : -1;
                        } catch { item.sugerencias = []; item.abierto = false; }
                    }, 280);
                },
                mover(item, delta) {
                    if (! item.sugerencias.length) return;
                    item.indice = (item.indice + delta + item.sugerencias.length) % item.sugerencias.length;
                    item.abierto = true;
                },
                confirmarProducto(item) {
                    if (item.abierto && item.indice >= 0 && item.sugerencias[item.indice]) {
                        this.elegirProducto(item, item.sugerencias[item.indice]);
                    }
                },
                elegirProducto(item, sug) {
                    item.producto_id = sug.id;
                    item.descripcion = sug.nombre;
                    item.q = sug.label;
                    if (! item.costo) item.costo = sug.precio_compra || null;
                    item.abierto = false;
                    item.sugerencias = [];
                },
                total() { return this.lineas.reduce((s, i) => s + (i.cantidad || 0) * (i.costo || 0), 0); },
                fmt(n) { return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
@endsection
