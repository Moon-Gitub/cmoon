@extends('layouts.app')

@section('titulo', 'Nueva compra')

@section('contenido')
    <form method="POST" action="{{ route('compras.store') }}" x-data="compraForm()" class="max-w-5xl space-y-4">
        @csrf

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            <div class="lg:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor *</label>
                <select name="proveedor_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($proveedores as $proveedor)
                        <option value="{{ $proveedor->id }}" {{ old('proveedor_id') == $proveedor->id ? 'selected' : '' }}>
                            {{ $proveedor->razon_social }} {{ $proveedor->cuit ? "({$proveedor->cuit})" : '' }}
                        </option>
                    @endforeach
                </select>
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
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-start gap-2">
                        <select :name="'items['+idx+'][producto_id]'" x-model="item.producto_id"
                                @change="precargarCosto(item)"
                                class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— Ítem libre (sin stock) —</option>
                            @foreach ($productos as $producto)
                                <option value="{{ $producto->id }}" data-costo="{{ $producto->precio_compra }}">
                                    {{ $producto->nombre }} ({{ $producto->codigo }})
                                </option>
                            @endforeach
                        </select>
                        <input type="text" :name="'items['+idx+'][descripcion]'" x-model="item.descripcion"
                               x-show="! item.producto_id" placeholder="Descripción"
                               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="number" :name="'items['+idx+'][cantidad]'" x-model.number="item.cantidad" required
                               step="any" min="0.001" placeholder="Cant."
                               class="w-24 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <input type="number" :name="'items['+idx+'][costo_unitario]'" x-model.number="item.costo" required
                               step="0.01" min="0" placeholder="Costo unit."
                               class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <span class="w-28 py-2 text-right text-sm font-semibold"
                              x-text="fmt((item.cantidad || 0) * (item.costo || 0))"></span>
                        <button type="button" @click="items.splice(idx, 1)" x-show="items.length > 1"
                                class="rounded-lg p-2 text-red-500 hover:bg-red-50">✕</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="items.push({ producto_id: '', descripcion: '', cantidad: 1, costo: null })"
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
                Registrar compra
            </button>
        </div>
    </form>

    <script>
        function compraForm() {
            return {
                items: [{ producto_id: '', descripcion: '', cantidad: 1, costo: null }],
                precargarCosto(item) {
                    const opt = document.querySelector(`option[value="${item.producto_id}"][data-costo]`);
                    if (opt && ! item.costo) item.costo = parseFloat(opt.dataset.costo) || null;
                },
                total() { return this.items.reduce((s, i) => s + (i.cantidad || 0) * (i.costo || 0), 0); },
                fmt(n) { return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
@endsection
