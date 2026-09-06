@extends('layouts.app')

@section('titulo', 'Nueva orden de compra')

@section('contenido')
    <form method="POST" action="{{ route('ordenes-compra.store') }}"
          x-data="ocForm(@js(route('busqueda.productos')))"
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
                <label class="mb-1 block text-sm font-medium text-slate-700">Sucursal *</label>
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
                <label class="mb-1 block text-sm font-medium text-slate-700">Estado</label>
                <select name="estado" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="borrador">Borrador</option>
                    <option value="enviada">Enviada</option>
                </select>
            </div>
            <div class="lg:col-span-3">
                <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
                <input type="text" name="observaciones" value="{{ old('observaciones') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <div class="mb-3 flex items-center justify-between gap-3">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Ítems</h2>
                <button type="button" @click="ocrAbierto = !ocrAbierto" class="text-sm text-indigo-600 hover:text-indigo-800">OCR / pegar texto</button>
            </div>

            <div x-show="ocrAbierto" x-cloak class="mb-4 rounded-lg border border-slate-200 bg-slate-50 p-3">
                <textarea x-model="ocrTexto" rows="4" placeholder="Pegá el texto de la factura…"
                          class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                <button type="button" @click="correrOcr()" :disabled="ocrLoading"
                        class="mt-2 rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-semibold text-white disabled:opacity-50">
                    <span x-text="ocrLoading ? 'Procesando…' : 'Interpretar con IA'"></span>
                </button>
                <p x-show="ocrError" class="mt-2 text-xs text-red-600" x-text="ocrError"></p>
            </div>

            <div class="space-y-2">
                <template x-for="(item, idx) in lineas" :key="idx">
                    <div class="flex flex-wrap items-start gap-2 sm:flex-nowrap">
                        <div class="relative min-w-[14rem] flex-1">
                            <input type="hidden" :name="'items['+idx+'][producto_id]'" :value="item.producto_id">
                            <input type="text" x-model="item.q"
                                   @input="buscarProducto(item)"
                                   placeholder="Código o producto…"
                                   autocomplete="off"
                                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <ul x-show="item.abierto && item.sugerencias.length" x-cloak
                                class="absolute z-30 mt-1 max-h-48 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
                                <template x-for="sug in item.sugerencias" :key="sug.id">
                                    <li>
                                        <button type="button" class="block w-full px-3 py-2 text-left hover:bg-indigo-50"
                                                @mousedown.prevent="elegirProducto(item, sug)">
                                            <span x-text="sug.label"></span>
                                        </button>
                                    </li>
                                </template>
                            </ul>
                        </div>
                        <input type="text" :name="'items['+idx+'][descripcion]'" x-model="item.descripcion"
                               placeholder="Descripción" class="min-w-[10rem] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="number" :name="'items['+idx+'][cantidad]'" x-model.number="item.cantidad" required
                               step="any" min="0.001" class="w-24 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <input type="number" :name="'items['+idx+'][precio_unitario]'" x-model.number="item.precio" required
                               step="0.01" min="0" class="w-28 rounded-lg border border-slate-300 px-2 py-2 text-right text-sm">
                        <span class="w-28 py-2 text-right text-sm font-semibold"
                              x-text="fmt((item.cantidad || 0) * (item.precio || 0))"></span>
                        <button type="button" @click="lineas.splice(idx, 1)" x-show="lineas.length > 1"
                                class="rounded-lg p-2 text-red-500 hover:bg-red-50">✕</button>
                    </div>
                </template>
            </div>
            <button type="button" @click="agregarLinea()" class="mt-3 text-sm text-indigo-600 hover:text-indigo-800">+ Agregar ítem</button>
            <p class="mt-4 text-right text-lg font-bold">TOTAL <span class="text-indigo-600" x-text="fmt(total())"></span></p>
        </div>

        @if ($errors->any())
            <div class="rounded-xl border border-red-200 bg-red-50 p-4 text-sm text-red-700">
                <ul class="list-inside list-disc">@foreach ($errors->all() as $e)<li>{{ $e }}</li>@endforeach</ul>
            </div>
        @endif

        <div class="flex flex-wrap gap-3">
            <a href="{{ route('ordenes-compra.index') }}" class="rounded-xl border border-slate-300 px-6 py-2.5 text-sm font-medium hover:bg-slate-50">Cancelar</a>
            <button class="rounded-xl bg-indigo-600 px-6 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">Guardar OC</button>
            @can('compras.gestionar')
                <button type="button" @click="crearCompraDesdeOcr()"
                        class="rounded-xl border border-emerald-300 bg-emerald-50 px-6 py-2.5 text-sm font-semibold text-emerald-800 hover:bg-emerald-100">
                    Crear compra desde OCR
                </button>
            @endcan
        </div>
    </form>

    <form id="form-compra-ocr" method="POST" action="{{ route('compras.desde-ocr') }}" class="hidden">
        @csrf
    </form>

    <script>
        function ocForm(urlProductos) {
            const lineaVacia = () => ({
                producto_id: '', descripcion: '', cantidad: 1, precio: null,
                q: '', sugerencias: [], abierto: false, _t: null,
            });
            return {
                urlProductos,
                lineas: [lineaVacia()],
                ocrAbierto: false,
                ocrTexto: '',
                ocrLoading: false,
                ocrError: '',
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
                        } catch { item.sugerencias = []; item.abierto = false; }
                    }, 280);
                },
                elegirProducto(item, sug) {
                    item.producto_id = sug.id;
                    item.descripcion = sug.nombre;
                    item.q = sug.label;
                    if (! item.precio) item.precio = sug.precio_compra || null;
                    item.abierto = false;
                    item.sugerencias = [];
                },
                async correrOcr() {
                    this.ocrError = '';
                    this.ocrLoading = true;
                    try {
                        const token = document.querySelector('meta[name="csrf-token"]')?.content
                            || document.querySelector('input[name="_token"]')?.value;
                        const res = await fetch(@js(route('ordenes-compra.ocr')), {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                Accept: 'application/json',
                                'X-CSRF-TOKEN': token,
                                'X-Requested-With': 'XMLHttpRequest',
                            },
                            body: JSON.stringify({ texto: this.ocrTexto }),
                        });
                        const data = await res.json();
                        if (! data.ok) {
                            this.ocrError = data.texto || 'No se pudo interpretar.';
                            return;
                        }
                        if (data.items?.length) {
                            this.lineas = data.items.map(i => ({
                                ...lineaVacia(),
                                descripcion: i.descripcion,
                                cantidad: i.cantidad || 1,
                                precio: i.costo_unitario || 0,
                                q: i.descripcion,
                            }));
                        }
                    } catch (e) {
                        this.ocrError = 'Error al llamar OCR.';
                    } finally {
                        this.ocrLoading = false;
                    }
                },
                crearCompraDesdeOcr() {
                    const proveedorId = document.querySelector('input[name="proveedor_id"]')?.value
                        || document.querySelector('select[name="proveedor_id"]')?.value;
                    if (! proveedorId) {
                        alert('Seleccioná un proveedor antes de crear la compra.');
                        return;
                    }
                    if (! this.lineas.length || ! this.lineas.some(l => (l.cantidad || 0) > 0)) {
                        alert('Interpretá el OCR o cargá al menos un ítem.');
                        return;
                    }

                    const form = document.getElementById('form-compra-ocr');
                    form.querySelectorAll('.ocr-dyn').forEach(el => el.remove());

                    const addHidden = (name, value) => {
                        const input = document.createElement('input');
                        input.type = 'hidden';
                        input.name = name;
                        input.value = value ?? '';
                        input.className = 'ocr-dyn';
                        form.appendChild(input);
                    };

                    addHidden('proveedor_id', proveedorId);
                    addHidden('sucursal_id', document.querySelector('select[name="sucursal_id"]')?.value || '');
                    addHidden('fecha', document.querySelector('input[name="fecha"]')?.value || '');
                    addHidden('observaciones', document.querySelector('input[name="observaciones"]')?.value || '');
                    addHidden('condicion', 'contado');
                    addHidden('sin_stock', '1');

                    this.lineas.forEach((item, idx) => {
                        addHidden(`items[${idx}][producto_id]`, item.producto_id || '');
                        addHidden(`items[${idx}][descripcion]`, item.descripcion || item.q || 'Ítem');
                        addHidden(`items[${idx}][cantidad]`, item.cantidad || 1);
                        addHidden(`items[${idx}][costo_unitario]`, item.precio ?? 0);
                    });

                    if (! confirm('¿Crear compra borrador (sin stock) con estos ítems?')) return;
                    form.submit();
                },
                total() { return this.lineas.reduce((s, i) => s + (i.cantidad || 0) * (i.precio || 0), 0); },
                fmt(n) { return '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },
            };
        }
    </script>
@endsection

