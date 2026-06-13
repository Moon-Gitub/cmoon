<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="theme-color" content="#0f172a">
    <link rel="manifest" href="/manifest.webmanifest">
    <title>Punto de venta — CMoon POS</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="h-screen overflow-hidden bg-slate-100 text-slate-900"
      x-data="posApp()" x-init="init()" x-cloak>

    <div class="flex h-full flex-col">

        {{-- Barra superior --}}
        <header class="flex items-center justify-between border-b border-slate-200 bg-slate-900 px-4 py-2 text-white">
            <div class="flex items-center gap-4">
                <a href="{{ route('dashboard') }}" class="flex items-center gap-2 text-sm text-slate-300 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    Volver
                </a>
                <h1 class="text-base font-bold tracking-tight">Punto de venta</h1>
                <span class="rounded-full bg-slate-700 px-2.5 py-0.5 text-xs">{{ $sucursal?->nombre ?? 'Sin sucursal' }}</span>
            </div>
            <div class="flex items-center gap-3 text-xs">
                <span x-show="online" class="flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-1 text-emerald-300">
                    <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span> En línea
                </span>
                <span x-show="! online" class="flex items-center gap-1.5 rounded-full bg-red-500/20 px-2.5 py-1 text-red-300">
                    <span class="h-1.5 w-1.5 animate-pulse rounded-full bg-red-400"></span> Sin conexión
                </span>
                <span x-show="pendientes.length" x-cloak
                      class="flex items-center gap-1.5 rounded-full bg-amber-500/20 px-2.5 py-1 text-amber-300"
                      x-text="pendientes.length + (pendientes.length === 1 ? ' venta por sincronizar' : ' ventas por sincronizar')"></span>
                @if ($sesionAbierta)
                    <span class="flex items-center gap-1.5 rounded-full bg-emerald-500/20 px-2.5 py-1 text-emerald-300">
                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-400"></span>
                        {{ $sesionAbierta->caja->nombre }} abierta
                    </span>
                @else
                    <a href="{{ route('cajas.index') }}" class="flex items-center gap-1.5 rounded-full bg-amber-500/20 px-2.5 py-1 text-amber-300 hover:bg-amber-500/30">
                        <span class="h-1.5 w-1.5 rounded-full bg-amber-400"></span>
                        Sin caja abierta
                    </a>
                @endif
                <span class="text-slate-400">{{ auth()->user()->name }}</span>
            </div>
        </header>

        <div class="flex min-h-0 flex-1">

            {{-- Columna izquierda: búsqueda + carrito --}}
            <main class="flex min-w-0 flex-1 flex-col p-4">
                <div class="relative">
                    <input type="text" x-ref="buscador" x-model="busqueda"
                           @input="filtrar()" @keydown.enter.prevent="agregarPorEnter()"
                           @keydown.arrow-down.prevent="moverSeleccion(1)" @keydown.arrow-up.prevent="moverSeleccion(-1)"
                           @keydown.escape="sugerencias = []"
                           placeholder="Escaneá un código de barras o escribí el nombre del producto…"
                           autofocus autocomplete="off"
                           class="w-full rounded-xl border-2 border-slate-300 bg-white px-4 py-3 text-lg shadow-sm focus:border-indigo-500 focus:outline-none focus:ring-4 focus:ring-indigo-100">

                    <div x-show="sugerencias.length" x-transition.opacity
                         class="absolute z-20 mt-1 max-h-80 w-full overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-xl">
                        <template x-for="(prod, i) in sugerencias" :key="prod.id">
                            <button type="button" @click="agregar(prod)"
                                    :class="i === seleccion ? 'bg-indigo-50' : ''"
                                    class="flex w-full items-center justify-between px-4 py-2.5 text-left hover:bg-indigo-50">
                                <span>
                                    <span class="font-medium" x-text="prod.nombre"></span>
                                    <span class="ml-2 font-mono text-xs text-slate-400" x-text="prod.codigo"></span>
                                </span>
                                <span class="font-semibold text-indigo-600" x-text="fmt(precioDe(prod))"></span>
                            </button>
                        </template>
                    </div>
                </div>

                <div class="mt-4 min-h-0 flex-1 overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Producto</th>
                                <th class="w-32 px-2 py-3 text-center">Cantidad</th>
                                <th class="w-28 px-2 py-3 text-right">Precio</th>
                                <th class="w-28 px-2 py-3 text-right">Total</th>
                                <th class="w-12 px-2 py-3"></th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            <template x-for="(item, idx) in carrito" :key="idx">
                                <tr>
                                    <td class="px-4 py-2">
                                        <p class="font-medium" x-text="item.nombre"></p>
                                        <p class="font-mono text-[11px] text-slate-400" x-text="item.codigo"></p>
                                    </td>
                                    <td class="px-2 py-2">
                                        <div class="flex items-center justify-center gap-1">
                                            <button type="button" @click="cambiarCantidad(idx, -1)"
                                                    class="h-7 w-7 rounded-lg border border-slate-300 text-sm font-bold hover:bg-slate-100">−</button>
                                            <input type="number" step="any" min="0.001" x-model.number="item.cantidad"
                                                   class="w-16 rounded-lg border border-slate-300 px-1 py-1 text-center text-sm">
                                            <button type="button" @click="cambiarCantidad(idx, 1)"
                                                    class="h-7 w-7 rounded-lg border border-slate-300 text-sm font-bold hover:bg-slate-100">+</button>
                                        </div>
                                    </td>
                                    <td class="px-2 py-2 text-right">
                                        <input type="number" step="0.01" min="0" x-model.number="item.precio"
                                               class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm">
                                    </td>
                                    <td class="px-2 py-2 text-right font-semibold" x-text="fmt(item.cantidad * item.precio)"></td>
                                    <td class="px-2 py-2 text-center">
                                        <button type="button" @click="carrito.splice(idx, 1)"
                                                class="rounded-lg p-1 text-red-500 hover:bg-red-50">
                                            <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M14.74 9l-.346 9m-4.788 0L9.26 9m9.968-3.21c.342.052.682.107 1.022.166m-1.022-.165L18.16 19.673a2.25 2.25 0 01-2.244 2.077H8.084a2.25 2.25 0 01-2.244-2.077L4.772 5.79m14.456 0a48.108 48.108 0 00-3.478-.397m-12 .562c.34-.059.68-.114 1.022-.165m0 0a48.11 48.11 0 013.478-.397m7.5 0v-.916c0-1.18-.91-2.164-2.09-2.201a51.964 51.964 0 00-3.32 0c-1.18.037-2.09 1.022-2.09 2.201v.916m7.5 0a48.667 48.667 0 00-7.5 0"/></svg>
                                        </button>
                                    </td>
                                </tr>
                            </template>
                            <tr x-show="! carrito.length">
                                <td colspan="5" class="px-4 py-16 text-center text-slate-400">
                                    <p class="text-lg">El carrito está vacío</p>
                                    <p class="mt-1 text-sm">Escaneá un producto o buscalo por nombre</p>
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </main>

            {{-- Columna derecha: cliente + totales + cobrar --}}
            <aside class="flex w-80 flex-col gap-3 border-l border-slate-200 bg-white p-4">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cliente</label>
                    <select x-model="clienteId"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none">
                        <option value="">Consumidor final</option>
                        <template x-for="c in clientes" :key="c.id">
                            <option :value="c.id" x-text="c.nombre + (c.documento ? ' (' + c.documento + ')' : '')"></option>
                        </template>
                    </select>
                    <p class="mt-1 text-xs text-indigo-600" x-show="listaActiva()" x-text="'Lista: ' + (listaActiva()?.nombre ?? '') + ' (' + (listaActiva()?.porcentaje > 0 ? '+' : '') + (listaActiva()?.porcentaje ?? 0) + '%)'"></p>
                </div>

                <div class="mt-auto space-y-2 border-t border-slate-100 pt-3 text-sm">
                    <div class="flex justify-between text-slate-600">
                        <span>Subtotal</span>
                        <span x-text="fmt(subtotal())"></span>
                    </div>
                    <div class="flex items-center justify-between text-slate-600">
                        <span>Descuento $</span>
                        <input type="number" step="0.01" min="0" x-model.number="descuento"
                               class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm">
                    </div>
                    <div class="flex justify-between border-t border-slate-200 pt-2 text-2xl font-bold">
                        <span>TOTAL</span>
                        <span class="text-indigo-600" x-text="fmt(total())"></span>
                    </div>
                </div>

                <button type="button" @click="abrirPago()" :disabled="! carrito.length"
                        class="w-full rounded-xl bg-indigo-600 py-4 text-lg font-bold text-white shadow-lg shadow-indigo-200 transition hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300 disabled:shadow-none">
                    COBRAR (F12)
                </button>
                <button type="button" @click="vaciar()" x-show="carrito.length"
                        class="w-full rounded-xl border border-red-200 py-2 text-sm font-medium text-red-600 hover:bg-red-50">
                    Vaciar carrito
                </button>
            </aside>
        </div>
    </div>

    {{-- Modal de cobro --}}
    <div x-show="modalPago" x-transition.opacity
         class="fixed inset-0 z-40 flex items-center justify-center bg-slate-900/60 p-4"
         @keydown.escape.window="modalPago = false">
        <div class="w-full max-w-lg rounded-2xl bg-white p-6 shadow-2xl" @click.outside="modalPago = false">
            <div class="mb-4 flex items-center justify-between">
                <h2 class="text-lg font-bold">Cobrar venta</h2>
                <p class="text-2xl font-bold text-indigo-600" x-text="fmt(total())"></p>
            </div>

            <div class="space-y-2">
                <template x-for="(pago, idx) in pagos" :key="idx">
                    <div class="flex items-center gap-2">
                        <select x-model.number="pago.medio_pago_id"
                                class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <template x-for="m in medios" :key="m.id">
                                <option :value="m.id" x-text="m.nombre + (m.recargo ? ' (' + (m.recargo > 0 ? '+' : '') + m.recargo + '%)' : '')"></option>
                            </template>
                        </select>
                        <input type="number" step="0.01" min="0" x-model.number="pago.importe"
                               class="w-32 rounded-lg border border-slate-300 px-3 py-2 text-right text-sm">
                        <button type="button" @click="pagos.splice(idx, 1)" x-show="pagos.length > 1"
                                class="rounded-lg p-1.5 text-red-500 hover:bg-red-50">✕</button>
                    </div>
                </template>
            </div>

            <button type="button" @click="pagos.push({ medio_pago_id: medios[0]?.id, importe: Math.max(0, redondear(total() - sumaPagos())) })"
                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                + Agregar otro medio de pago
            </button>

            <div class="mt-4 space-y-1 rounded-xl bg-slate-50 p-3 text-sm">
                <div class="flex justify-between">
                    <span class="text-slate-500">Pagos cargados</span>
                    <span class="font-semibold" x-text="fmt(sumaPagos())"></span>
                </div>
                <div class="flex justify-between" x-show="redondear(sumaPagos() - total()) !== 0">
                    <span class="text-slate-500" x-text="sumaPagos() < total() ? 'Falta' : 'Sobra'"></span>
                    <span class="font-semibold text-red-600" x-text="fmt(Math.abs(redondear(sumaPagos() - total())))"></span>
                </div>
                <div class="flex items-center justify-between border-t border-slate-200 pt-2">
                    <span class="text-slate-500">Paga con (efectivo)</span>
                    <input type="number" step="0.01" min="0" x-model.number="recibido"
                           class="w-28 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm">
                </div>
                <div class="flex justify-between" x-show="recibido > 0">
                    <span class="font-semibold text-slate-700">Vuelto</span>
                    <span class="text-lg font-bold text-emerald-600" x-text="fmt(Math.max(0, redondear(recibido - total())))"></span>
                </div>
            </div>

            <p class="mt-2 text-sm text-red-600" x-show="errorPago" x-text="errorPago"></p>

            <div class="mt-4 flex gap-3">
                <button type="button" @click="modalPago = false"
                        class="flex-1 rounded-xl border border-slate-300 py-3 font-medium hover:bg-slate-50">
                    Cancelar
                </button>
                <button type="button" @click="confirmar()" :disabled="procesando"
                        class="flex-1 rounded-xl bg-emerald-600 py-3 font-bold text-white hover:bg-emerald-700 disabled:bg-slate-300">
                    <span x-show="! procesando">CONFIRMAR (Enter)</span>
                    <span x-show="procesando">Guardando…</span>
                </button>
            </div>
        </div>
    </div>

    {{-- Aviso de venta confirmada --}}
    <div x-show="ventaOk" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/60 p-4">
        <div class="w-full max-w-sm rounded-2xl bg-white p-8 text-center shadow-2xl">
            <div class="mx-auto mb-4 flex h-16 w-16 items-center justify-center rounded-full"
                 :class="ventaOk?.offline ? 'bg-amber-100' : 'bg-emerald-100'">
                <svg x-show="! ventaOk?.offline" class="h-9 w-9 text-emerald-600" fill="none" stroke="currentColor" stroke-width="2.5" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 12.75l6 6 9-13.5"/></svg>
                <svg x-show="ventaOk?.offline" class="h-9 w-9 text-amber-600" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0 3.75h.008v.008H12v-.008zM21.75 12a9.75 9.75 0 11-19.5 0 9.75 9.75 0 0119.5 0z"/></svg>
            </div>
            <h2 class="text-xl font-bold" x-text="ventaOk?.offline ? 'Venta guardada sin conexión' : 'Venta registrada'"></h2>
            <p class="mt-1 text-slate-500" x-show="! ventaOk?.offline">Comprobante <span class="font-semibold" x-text="'#' + (ventaOk?.numero ?? '')"></span> · <span x-text="fmt(ventaOk?.total ?? 0)"></span></p>
            <p class="mt-1 text-sm text-amber-600" x-show="ventaOk?.offline">
                Total <span class="font-semibold" x-text="fmt(ventaOk?.total ?? 0)"></span>.
                Se va a sincronizar sola cuando vuelva internet.
            </p>
            <div class="mt-6 flex gap-3">
                <button type="button" @click="imprimirTicket()" x-show="! ventaOk?.offline"
                        class="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-medium hover:bg-slate-50">
                    Imprimir ticket
                </button>
                <button type="button" @click="ventaOk = null; $refs.buscador.focus()"
                        class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                    Nueva venta (Esc)
                </button>
            </div>
        </div>
    </div>

    <script>
        function posApp() {
            return {
                productos: [], clientes: [], listas: [], medios: [],
                carrito: [], busqueda: '', sugerencias: [], seleccion: 0,
                clienteId: '', descuento: 0,
                modalPago: false, pagos: [], recibido: 0, errorPago: '',
                procesando: false, ventaOk: null,
                online: navigator.onLine, pendientes: [], sincronizando: false,
                sucursalId: {{ $sucursal?->id ?? 'null' }},
                cajaSesionId: {{ $sesionAbierta?->id ?? 'null' }},
                presupuestoId: {{ $presupuesto?->id ?? 'null' }},

                async init() {
                    window.addEventListener('keydown', (e) => {
                        if (e.key === 'F12') { e.preventDefault(); if (this.carrito.length) this.abrirPago(); }
                        if (e.key === 'Enter' && this.modalPago && ! this.procesando) { e.preventDefault(); this.confirmar(); }
                        if (e.key === 'Escape' && this.ventaOk) { this.ventaOk = null; this.$refs.buscador.focus(); }
                    });

                    // Modo offline: service worker + cola de ventas pendientes
                    if ('serviceWorker' in navigator) {
                        navigator.serviceWorker.register('/sw.js').catch(() => {});
                    }
                    this.pendientes = JSON.parse(localStorage.getItem('pos_pendientes') ?? '[]');
                    window.addEventListener('online', () => { this.online = true; this.sincronizar(); });
                    window.addEventListener('offline', () => { this.online = false; });
                    setInterval(() => this.sincronizar(), 30000);
                    this.sincronizar();

                    try {
                        const res = await fetch('{{ route('pos.catalogo') }}', { headers: { 'Accept': 'application/json' } });
                        const data = await res.json();
                        Object.assign(this, data);
                        localStorage.setItem('pos_catalogo', JSON.stringify(data));
                    } catch {
                        // Sin conexión: usar el último catálogo conocido
                        const cache = localStorage.getItem('pos_catalogo');
                        if (cache) Object.assign(this, JSON.parse(cache));
                    }

                    @if ($presupuesto)
                        // Carrito precargado desde el presupuesto #{{ $presupuesto->numero }}
                        this.carrito = @json($presupuestoItems);
                        this.clienteId = '{{ $presupuesto->cliente_id ?? '' }}';
                    @endif
                },

                guardarPendientes() {
                    localStorage.setItem('pos_pendientes', JSON.stringify(this.pendientes));
                },

                async enviarVenta(payload) {
                    const res = await fetch('{{ route('pos.guardar') }}', {
                        method: 'POST',
                        headers: {
                            'Content-Type': 'application/json',
                            'Accept': 'application/json',
                            'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                        },
                        body: JSON.stringify(payload),
                    });
                    return res;
                },

                async sincronizar() {
                    if (this.sincronizando || ! navigator.onLine || ! this.pendientes.length) return;
                    this.sincronizando = true;
                    try {
                        const restantes = [];
                        for (const payload of this.pendientes) {
                            try {
                                const res = await this.enviarVenta({ ...payload, origen: 'offline' });
                                // 422 = inválida (no se reintenta); otros errores se reintentan
                                if (! res.ok && res.status !== 422) restantes.push(payload);
                            } catch {
                                restantes.push(payload);
                            }
                        }
                        this.pendientes = restantes;
                        this.guardarPendientes();
                    } finally {
                        this.sincronizando = false;
                    }
                },

                listaActiva() {
                    const c = this.clientes.find(c => c.id == this.clienteId);
                    if (! c || ! c.lista_precio_id) return null;
                    return this.listas.find(l => l.id == c.lista_precio_id) ?? null;
                },

                precioDe(prod) {
                    const lista = this.listaActiva();
                    const precio = lista ? prod.precio * (1 + lista.porcentaje / 100) : prod.precio;
                    return Math.round(precio * 100) / 100;
                },

                filtrar() {
                    const q = this.busqueda.trim().toLowerCase();
                    this.seleccion = 0;
                    if (q.length < 2) { this.sugerencias = []; return; }
                    this.sugerencias = this.productos
                        .filter(p => p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q))
                        .slice(0, 8);
                },

                moverSeleccion(dir) {
                    if (! this.sugerencias.length) return;
                    this.seleccion = (this.seleccion + dir + this.sugerencias.length) % this.sugerencias.length;
                },

                agregarPorEnter() {
                    const q = this.busqueda.trim();
                    if (! q) return;

                    // Código de balanza EAN-13 (prefijo 2): 2 + PLU(5) + peso en gramos(5) + verificador
                    if (/^2\d{12}$/.test(q)) {
                        const plu = q.slice(1, 6);
                        const gramos = parseInt(q.slice(6, 11), 10);
                        const producto = this.productos.find(p =>
                            p.pesable && (p.codigo === plu || p.codigo === String(parseInt(plu, 10))));
                        if (producto && gramos > 0) {
                            this.carrito.push({
                                producto_id: producto.id,
                                codigo: q,
                                nombre: producto.nombre,
                                cantidad: gramos / 1000,
                                precio: this.precioDe(producto),
                                iva: producto.iva,
                            });
                            this.busqueda = '';
                            this.sugerencias = [];
                            return;
                        }
                    }

                    const exacto = this.productos.find(p => p.codigo.toLowerCase() === q.toLowerCase());
                    if (exacto) { this.agregar(exacto); return; }
                    if (this.sugerencias.length) this.agregar(this.sugerencias[this.seleccion]);
                },

                agregar(prod) {
                    const existente = this.carrito.find(i => i.producto_id === prod.id);
                    if (existente && ! prod.pesable) {
                        existente.cantidad += 1;
                    } else {
                        this.carrito.push({
                            producto_id: prod.id,
                            codigo: prod.codigo,
                            nombre: prod.nombre,
                            cantidad: 1,
                            precio: this.precioDe(prod),
                            iva: prod.iva,
                        });
                    }
                    this.busqueda = '';
                    this.sugerencias = [];
                    this.$refs.buscador.focus();
                },

                cambiarCantidad(idx, dir) {
                    const item = this.carrito[idx];
                    item.cantidad = Math.max(0.001, Math.round((item.cantidad + dir) * 1000) / 1000);
                },

                redondear(n) { return Math.round(n * 100) / 100; },
                subtotal() { return this.redondear(this.carrito.reduce((s, i) => s + i.cantidad * i.precio, 0)); },
                total() { return Math.max(0, this.redondear(this.subtotal() - (this.descuento || 0))); },
                sumaPagos() { return this.redondear(this.pagos.reduce((s, p) => s + (p.importe || 0), 0)); },
                fmt(n) { return '$ ' + (n ?? 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 }); },

                abrirPago() {
                    const efectivo = this.medios.find(m => m.tipo === 'efectivo');
                    this.pagos = [{ medio_pago_id: efectivo?.id ?? this.medios[0]?.id, importe: this.total() }];
                    this.recibido = 0;
                    this.errorPago = '';
                    this.modalPago = true;
                },

                vaciar() {
                    if (confirm('¿Vaciar el carrito?')) { this.carrito = []; this.$refs.buscador.focus(); }
                },

                async confirmar() {
                    if (this.redondear(this.sumaPagos() - this.total()) !== 0) {
                        this.errorPago = 'La suma de los pagos tiene que coincidir con el total.';
                        return;
                    }
                    this.procesando = true;
                    this.errorPago = '';

                    const payload = {
                        uuid: crypto.randomUUID(),
                        presupuesto_id: this.presupuestoId,
                        sucursal_id: this.sucursalId,
                        caja_sesion_id: this.cajaSesionId,
                        cliente_id: this.clienteId || null,
                        descuento: this.descuento || 0,
                        fecha: new Date().toISOString(),
                        origen: 'pos',
                        items: this.carrito.map(i => ({
                            producto_id: i.producto_id,
                            descripcion: i.nombre,
                            cantidad: i.cantidad,
                            precio_unitario: i.precio,
                            alicuota_iva: i.iva,
                        })),
                        pagos: this.pagos.filter(p => p.importe > 0),
                    };

                    try {
                        const res = await this.enviarVenta(payload);
                        const data = await res.json();
                        if (! res.ok) {
                            this.errorPago = data.message ?? 'Error al guardar la venta.';
                            return;
                        }
                        this.ventaOk = data;
                        this.finalizarVenta();
                    } catch {
                        // Sin conexión: la venta queda en cola y se sincroniza sola
                        this.pendientes.push(payload);
                        this.guardarPendientes();
                        this.ventaOk = { offline: true, total: this.total() };
                        this.finalizarVenta();
                    } finally {
                        this.procesando = false;
                    }
                },

                finalizarVenta() {
                    this.modalPago = false;
                    this.carrito = [];
                    this.descuento = 0;
                    this.clienteId = '';
                    this.presupuestoId = null;
                },

                imprimirTicket() {
                    if (this.ventaOk?.ticket_url) {
                        window.open(this.ventaOk.ticket_url + '?print=1', '_blank', 'width=400,height=600');
                    }
                },
            };
        }
    </script>
    <style>[x-cloak] { display: none !important; }</style>
</body>
</html>
