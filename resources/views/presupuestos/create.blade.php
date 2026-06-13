@extends('layouts.app')

@section('titulo', 'Nuevo presupuesto')

@section('contenido')
    <form method="POST" action="{{ route('presupuestos.store') }}" x-data="presupuestoForm()" class="max-w-5xl space-y-4">
        @csrf

        <div class="grid grid-cols-1 gap-4 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Cliente</label>
                <select name="cliente_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Consumidor final</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}">{{ $cliente->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Válido hasta</label>
                <input type="date" name="valido_hasta" value="{{ old('valido_hasta', now()->addDays(15)->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
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
                                @change="precargar(item)"
                                class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">— Ítem libre —</option>
                            @foreach ($productos as $producto)
                                <option value="{{ $producto->id }}" data-precio="{{ $producto->precio_venta }}">
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
                        <input type="number" :name="'items['+idx+'][precio_unitario]'" x-model.number="item.precio" required
                               step="0.01" min="0" placeholder="Precio"
                               class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <span class="w-28 py-2 text-right text-sm font-semibold"
                              x-text="fmt((item.cantidad || 0) * (item.precio || 0))"></span>
                        <button type="button" @click="items.splice(idx, 1)" x-show="items.length > 1"
                                class="rounded-lg p-2 text-red-500 hover:bg-red-50">✕</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="items.push({ producto_id: '', descripcion: '', cantidad: 1, precio: null })"
                    class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Agregar ítem</button>

            <div class="mt-4 flex justify-end border-t border-slate-100 pt-3 text-lg font-bold">
                TOTAL&nbsp;<span class="text-indigo-600" x-text="fmt(total())"></span>
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
            <a href="{{ route('presupuestos.index') }}"
               class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-medium hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                Crear presupuesto
            </button>
        </div>
    </form>

    <script>
        function presupuestoForm() {
            return {
                items: [{ producto_id: '', descripcion: '', cantidad: 1, precio: null }],
                precargar(item) {
                    const opt = document.querySelector(`option[value="${item.producto_id}"][data-precio]`);
                    if (opt && ! item.precio) item.precio = parseFloat(opt.dataset.precio) || null;
                },
                total() { return this.items.reduce((s, i) => s + (i.cantidad || 0) * (i.precio || 0), 0); },
                fmt(n) { return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
@endsection
