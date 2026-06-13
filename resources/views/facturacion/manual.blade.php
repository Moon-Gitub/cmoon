@extends('layouts.app')

@section('titulo', 'Factura manual')

@section('contenido')
    <form method="POST" action="{{ route('facturacion.manual.store') }}"
          x-data="facturaManual()" class="max-w-4xl space-y-4">
        @csrf

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Emisor</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">CUIT emisor *</label>
                    <select name="emisor_id" x-model="emisorId" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($emisores as $emisor)
                            <option value="{{ $emisor->id }}">{{ $emisor->razon_social }} ({{ $emisor->cuit }})</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Punto de venta *</label>
                    @foreach ($emisores as $emisor)
                        <select name="punto_venta_id" x-show="emisorId == '{{ $emisor->id }}'"
                                :disabled="emisorId != '{{ $emisor->id }}'"
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($emisor->puntosVenta->where('activo', true) as $pv)
                                <option value="{{ $pv->id }}">{{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }} {{ $pv->descripcion }}</option>
                            @endforeach
                        </select>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Receptor</h2>
            <div class="mb-3">
                <label class="mb-1 block text-sm font-medium text-slate-700">Cargar desde cliente</label>
                <select @change="cargarCliente($event.target.value)"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Completar a mano —</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}"
                                data-nombre="{{ $cliente->nombre }}"
                                data-tipo="{{ $cliente->tipo_documento }}"
                                data-doc="{{ $cliente->documento }}"
                                data-iva="{{ $cliente->condicion_iva }}">{{ $cliente->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre / Razón social</label>
                    <input type="text" name="receptor_nombre" x-model="receptor.nombre" placeholder="Consumidor final"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Condición IVA *</label>
                    <select name="receptor_condicion_iva" x-model="receptor.condicionIva"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="CONSUMIDOR_FINAL">Consumidor final</option>
                        <option value="RESPONSABLE_INSCRIPTO">Responsable Inscripto</option>
                        <option value="MONOTRIBUTO">Monotributo</option>
                        <option value="EXENTO">Exento</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de documento *</label>
                    <select name="doc_tipo" x-model="receptor.docTipo"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="99">Sin identificar</option>
                        <option value="96">DNI</option>
                        <option value="80">CUIT</option>
                        <option value="86">CUIL</option>
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Número de documento</label>
                    <input type="text" name="doc_numero" x-model="receptor.docNumero"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Ítems (precios con IVA incluido)</h2>
            <div class="space-y-2">
                <template x-for="(item, idx) in items" :key="idx">
                    <div class="flex items-start gap-2">
                        <input type="text" :name="'items['+idx+'][descripcion]'" x-model="item.descripcion" required
                               placeholder="Descripción"
                               class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="number" :name="'items['+idx+'][cantidad]'" x-model.number="item.cantidad" required
                               step="any" min="0.001" placeholder="Cant."
                               class="w-20 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <input type="number" :name="'items['+idx+'][precio_unitario]'" x-model.number="item.precio" required
                               step="0.01" min="0.01" placeholder="Precio"
                               class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <select :name="'items['+idx+'][alicuota_iva]'" x-model="item.iva"
                                class="w-24 rounded-lg border border-slate-300 px-2 py-2 text-sm">
                            <option value="21">21%</option>
                            <option value="10.5">10,5%</option>
                            <option value="27">27%</option>
                            <option value="0">0%</option>
                        </select>
                        <button type="button" @click="items.splice(idx, 1)" x-show="items.length > 1"
                                class="rounded-lg p-2 text-red-500 hover:bg-red-50">✕</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="items.push({ descripcion: '', cantidad: 1, precio: null, iva: '21' })"
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
            <a href="{{ route('facturacion.index') }}"
               class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-medium hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                Emitir y solicitar CAE
            </button>
        </div>
    </form>

    <script>
        function facturaManual() {
            return {
                emisorId: '{{ $emisores->first()?->id }}',
                receptor: { nombre: '', condicionIva: 'CONSUMIDOR_FINAL', docTipo: '99', docNumero: '' },
                items: [{ descripcion: '', cantidad: 1, precio: null, iva: '21' }],
                cargarCliente(id) {
                    const opt = document.querySelector(`option[value="${id}"][data-nombre]`);
                    if (! opt) return;
                    this.receptor.nombre = opt.dataset.nombre;
                    this.receptor.docNumero = opt.dataset.doc ?? '';
                    this.receptor.condicionIva = opt.dataset.iva || 'CONSUMIDOR_FINAL';
                    this.receptor.docTipo = { CUIT: '80', CUIL: '86', DNI: '96' }[opt.dataset.tipo] ?? '99';
                },
                total() { return this.items.reduce((s, i) => s + (i.cantidad || 0) * (i.precio || 0), 0); },
                fmt(n) { return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
@endsection
