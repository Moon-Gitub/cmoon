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
<body class="min-h-screen overflow-x-hidden bg-slate-100 text-slate-900 lg:h-screen lg:overflow-hidden"
      x-data="posApp()" x-init="init()" x-cloak>

    <div class="flex min-h-screen flex-col lg:h-full">

        {{-- Barra superior --}}
        <header class="flex flex-wrap items-center justify-between gap-2 border-b border-slate-200 bg-slate-900 px-3 py-2 text-white sm:px-4">
            <div class="flex min-w-0 items-center gap-2 sm:gap-4">
                <a href="{{ route('dashboard') }}" class="flex shrink-0 items-center gap-2 text-sm text-slate-300 hover:text-white">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5L3 12m0 0l7.5-7.5M3 12h18"/></svg>
                    <span class="hidden sm:inline">Volver</span>
                </a>
                <h1 class="truncate text-sm font-bold tracking-tight sm:text-base">Punto de venta</h1>
                <span class="hidden rounded-full bg-slate-700 px-2.5 py-0.5 text-xs sm:inline">{{ $sucursal?->nombre ?? 'Sin sucursal' }}</span>
            </div>
            <div class="flex flex-wrap items-center justify-end gap-2 text-xs">
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

        <div class="flex min-h-0 flex-1 flex-col lg:flex-row">

            {{-- Columna izquierda: búsqueda + carrito --}}
            <main class="flex min-h-0 min-w-0 flex-1 flex-col p-3 sm:p-4">
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

                <div class="mt-3 min-h-0 flex-1 overflow-x-auto overflow-y-auto rounded-xl border border-slate-200 bg-white shadow-sm sm:mt-4">
                    <table class="min-w-[640px] w-full text-sm">
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
            <aside class="flex w-full shrink-0 flex-col gap-3 border-t border-slate-200 bg-white p-3 sm:p-4 lg:w-80 lg:border-l lg:border-t-0">
                <div>
                    <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cliente</label>
                    <select x-model="clienteId" @change="errorPago = ''"
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
                        <select x-model.number="pago.medio_pago_id" @change="onCambioMedio(idx)"
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

            <button type="button" @click="agregarMedioPago()"
                    class="mt-2 text-sm text-indigo-600 hover:text-indigo-800">
                + Agregar otro medio de pago
            </button>

            <p class="mt-2 rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800" x-show="requiereCliente()" x-cloak>
                Seleccioná un cliente en el panel lateral para vender en cuenta corriente.
            </p>

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
            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button type="button" @click="imprimirTicket()" x-show="! ventaOk?.offline && ! ventaOk?.factura_url"
                        class="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-medium hover:bg-slate-50">
                    Imprimir ticket
                </button>
                <button type="button" @click="facturarVenta()"
                        x-show="puedeFacturar && emisores.length && ! ventaOk?.offline && ! ventaOk?.facturada"
                        :disabled="facturando"
                        class="flex-1 rounded-xl border border-indigo-300 bg-indigo-50 py-2.5 text-sm font-semibold text-indigo-700 hover:bg-indigo-100 disabled:opacity-50">
                    <span x-show="! facturando">Facturar (AFIP)</span>
                    <span x-show="facturando">Autorizando…</span>
                </button>
                <a x-show="ventaOk?.factura_url" :href="ventaOk?.factura_url" target="_blank"
                   class="flex-1 rounded-xl border border-emerald-300 bg-emerald-50 py-2.5 text-center text-sm font-semibold text-emerald-700 hover:bg-emerald-100">
                    Ver factura
                </a>
                <button type="button" @click="ventaOk = null; $refs.buscador.focus()"
                        class="flex-1 rounded-xl bg-indigo-600 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                    Nueva venta (Esc)
                </button>
            </div>
            <p class="mt-3 text-xs text-red-600" x-show="errorFactura" x-text="errorFactura"></p>
            <p class="mt-2 text-xs text-emerald-700" x-show="ventaOk?.facturada" x-text="ventaOk?.factura_msg"></p>
        </div>
    </div>

    {{-- Modal cobro QR Mercado Pago --}}
    <div x-show="modalQr" x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/70 p-4">
        <div class="w-full max-w-md rounded-2xl bg-white p-6 text-center shadow-2xl">
            <h2 class="text-lg font-bold">Pagá con Mercado Pago</h2>
            <p class="mt-1 text-sm text-slate-500">Escaneá el QR con la app de Mercado Pago</p>
            <p class="mt-2 text-2xl font-bold text-indigo-600" x-text="fmt(qrTotal)"></p>
            <div class="mx-auto mt-4 flex max-h-64 items-center justify-center overflow-hidden rounded-xl border border-slate-200 bg-white p-3"
                 x-html="qrSvg"></div>
            <p class="mt-3 text-sm text-slate-600" x-show="qrEsperando">Esperando confirmación del pago…</p>
            <p class="mt-3 text-sm text-red-600" x-show="errorQr" x-text="errorQr"></p>
            <div class="mt-4 flex gap-3">
                <button type="button" @click="cancelarQr()"
                        class="flex-1 rounded-xl border border-slate-300 py-2.5 text-sm font-medium hover:bg-slate-50">
                    Cancelar
                </button>
            </div>
        </div>
    </div>

    <script>
        function posApp() {
            return {
                productos: [], clientes: [], listas: [], medios: [], emisores: [],
                mercadopagoQr: false, puedeFacturar: @json($puedeFacturar ?? false),
                emisorId: null, puntoVentaId: null,
                carrito: [], busqueda: '', sugerencias: [], seleccion: 0,
                clienteId: '', descuento: 0,
                modalPago: false, pagos: [], recibido: 0, errorPago: '',
                modalQr: false, qrSvg: '', qrReferencia: '', qrTotal: 0, qrEsperando: false,
                qrPollTimer: null, errorQr: '',
                procesando: false, ventaOk: null, facturando: false, errorFactura: '',
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
                        if (this.emisores?.length) {
                            this.emisorId = this.emisores[0].id;
                            this.puntoVentaId = this.emisores[0].puntos_venta?.[0]?.id ?? null;
                        }
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

                medioPorId(id) {
                    return this.medios.find(m => Number(m.id) === Number(id));
                },

                medioEfectivo() {
                    return this.medios.find(m => m.tipo === 'efectivo') ?? this.medios[0];
                },

                requiereCliente() {
                    return this.pagos.some(p => this.medioPorId(p.medio_pago_id)?.tipo === 'cuenta_corriente') && ! this.clienteId;
                },

                usaQrMercadoPago() {
                    return this.pagos.some(p => this.medioPorId(p.medio_pago_id)?.tipo === 'qr');
                },

                importeQr() {
                    return this.redondear(this.pagos
                        .filter(p => this.medioPorId(p.medio_pago_id)?.tipo === 'qr')
                        .reduce((s, p) => s + (p.importe || 0), 0));
                },

                onCambioMedio(idx) {
                    this.errorPago = '';
                    const medio = this.medioPorId(this.pagos[idx].medio_pago_id);
                    if (medio?.tipo === 'cuenta_corriente' && ! this.clienteId) {
                        this.errorPago = 'Seleccioná un cliente para vender en cuenta corriente.';
                    }
                },

                agregarMedioPago() {
                    const efectivo = this.medioEfectivo();
                    this.pagos.push({
                        medio_pago_id: efectivo?.id ?? this.medios[0]?.id,
                        importe: Math.max(0, this.redondear(this.total() - this.sumaPagos())),
                    });
                },

                parsearError(res, data) {
                    if (data?.errors) {
                        return Object.values(data.errors).flat().join(' ');
                    }
                    return data?.message ?? 'Error al guardar la venta.';
                },

                abrirPago() {
                    const efectivo = this.medioEfectivo();
                    this.pagos = [{ medio_pago_id: efectivo?.id ?? this.medios[0]?.id, importe: this.total() }];
                    this.recibido = 0;
                    this.errorPago = '';
                    this.modalPago = true;
                },

                vaciar() {
                    if (confirm('¿Vaciar el carrito?')) { this.carrito = []; this.$refs.buscador.focus(); }
                },

                async confirmar() {
                    if (this.requiereCliente()) {
                        this.errorPago = 'Para cuenta corriente tenés que seleccionar un cliente.';
                        return;
                    }
                    if (this.redondear(this.sumaPagos() - this.total()) !== 0) {
                        this.errorPago = 'La suma de los pagos tiene que coincidir con el total.';
                        return;
                    }
                    if (this.usaQrMercadoPago()) {
                        await this.iniciarCobroQr();
                        return;
                    }
                    await this.registrarVenta();
                },

                armarPayload() {
                    return {
                        uuid: crypto.randomUUID(),
                        presupuesto_id: this.presupuestoId,
                        sucursal_id: this.sucursalId,
                        caja_sesion_id: this.cajaSesionId,
                        cliente_id: this.clienteId ? Number(this.clienteId) : null,
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
                },

                async registrarVenta() {
                    this.procesando = true;
                    this.errorPago = '';
                    const payload = this.armarPayload();

                    try {
                        const res = await this.enviarVenta(payload);
                        const data = await res.json();
                        if (! res.ok) {
                            this.errorPago = this.parsearError(res, data);
                            return;
                        }
                        this.ventaOk = data;
                        this.finalizarVenta();
                    } catch {
                        this.pendientes.push(payload);
                        this.guardarPendientes();
                        this.ventaOk = { offline: true, total: this.total() };
                        this.finalizarVenta();
                    } finally {
                        this.procesando = false;
                    }
                },

                async iniciarCobroQr() {
                    if (! this.mercadopagoQr) {
                        this.errorPago = 'Mercado Pago QR no está configurado en el servidor.';
                        return;
                    }
                    this.procesando = true;
                    this.errorPago = '';
                    this.errorQr = '';
                    const referencia = crypto.randomUUID();
                    const totalQr = this.importeQr() || this.total();

                    try {
                        const res = await fetch('{{ route('pos.qr.crear') }}', {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({
                                total: totalQr,
                                titulo: 'Venta POS CMoon',
                                referencia,
                            }),
                        });
                        const data = await res.json();
                        if (! res.ok) {
                            this.errorPago = data.message ?? 'No se pudo generar el QR.';
                            return;
                        }
                        this.qrReferencia = data.referencia;
                        this.qrTotal = totalQr;
                        this.qrSvg = data.qr_svg ?? '';
                        this.modalPago = false;
                        this.modalQr = true;
                        this.qrEsperando = true;
                        this.esperarPagoQr();
                    } catch {
                        this.errorPago = 'Error de conexión al generar el QR.';
                    } finally {
                        this.procesando = false;
                    }
                },

                esperarPagoQr() {
                    clearInterval(this.qrPollTimer);
                    let intentos = 0;
                    this.qrPollTimer = setInterval(async () => {
                        intentos++;
                        try {
                            const res = await fetch(`{{ route('pos.qr.estado') }}?referencia=${encodeURIComponent(this.qrReferencia)}`, {
                                headers: { 'Accept': 'application/json' },
                            });
                            const data = await res.json();
                            if (data.aprobado) {
                                clearInterval(this.qrPollTimer);
                                this.modalQr = false;
                                this.qrEsperando = false;
                                await this.registrarVenta();
                            } else if (intentos > 120) {
                                this.errorQr = 'Tiempo de espera agotado. Cancelá e intentá de nuevo.';
                                this.qrEsperando = false;
                                clearInterval(this.qrPollTimer);
                            }
                        } catch {
                            // reintenta en el próximo ciclo
                        }
                    }, 3000);
                },

                cancelarQr() {
                    clearInterval(this.qrPollTimer);
                    this.modalQr = false;
                    this.qrEsperando = false;
                    this.errorQr = '';
                    this.modalPago = true;
                },

                async facturarVenta() {
                    if (! this.ventaOk?.id || ! this.emisorId || ! this.puntoVentaId) {
                        this.errorFactura = 'No hay emisor o punto de venta configurado.';
                        return;
                    }
                    this.facturando = true;
                    this.errorFactura = '';
                    try {
                        const res = await fetch(`{{ url('/pos/ventas') }}/${this.ventaOk.id}/facturar`, {
                            method: 'POST',
                            headers: {
                                'Content-Type': 'application/json',
                                'Accept': 'application/json',
                                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
                            },
                            body: JSON.stringify({
                                emisor_id: this.emisorId,
                                punto_venta_id: this.puntoVentaId,
                            }),
                        });
                        const data = await res.json();
                        if (data.estado === 'autorizado') {
                            this.ventaOk.facturada = true;
                            this.ventaOk.factura_url = data.factura_url;
                            this.ventaOk.factura_msg = `${data.tipo} ${data.numero} · CAE ${data.cae}`;
                        } else {
                            this.errorFactura = data.mensaje ?? 'AFIP no autorizó el comprobante.';
                        }
                    } catch {
                        this.errorFactura = 'Error al conectar con el servidor de facturación.';
                    } finally {
                        this.facturando = false;
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
