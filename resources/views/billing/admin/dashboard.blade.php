@extends('billing.admin.layout')
@section('titulo', 'Tablero')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    @php($tableroJson = json_encode(['serie' => $serie, 'deudores' => $deudores, 'aging' => $aging, 'niveles' => $niveles], JSON_UNESCAPED_UNICODE))
    <script type="application/json" id="cobros-datos">{!! $tableroJson !!}</script>

    <div x-data="cobrosTablero()" x-cloak>
        <div class="mb-7">
            <h1 class="font-display text-[2rem] font-medium tracking-tight text-[var(--md-text)]">Tablero</h1>
            <p class="mt-1 text-[1.05rem] text-[var(--md-text-mute)]">Resumen de cobranza · {{ $periodo }}</p>
        </div>

        <div class="md-kpi mb-6">
            <div>
                <div class="label">Deuda total</div>
                <div class="value">{{ Money::formato($deudaTotal) }}</div>
            </div>
            <div>
                <div class="label">Facturado {{ $periodo }}</div>
                <div class="value">{{ Money::formato($facturado) }}</div>
            </div>
            <div>
                <div class="label">Cobrado {{ $periodo }}</div>
                <div class="value">{{ Money::formato($cobrado) }}@if ($tasa !== null)<span class="ml-2 text-base font-normal text-[var(--md-text-mute)]">{{ $tasa }}%</span>@endif</div>
            </div>
            <div>
                <div class="label">Abono mensual (MRR)</div>
                <div class="value">{{ Money::formato($mrr) }}</div>
            </div>
        </div>

        <div class="md-panel p-6">
            <div class="flex flex-wrap items-center justify-between gap-4">
                <div>
                    <p class="text-lg font-semibold text-[var(--md-text)]">Cargos y cobros</p>
                    <p class="text-[var(--md-text-mute)]">Pasá el mouse por un mes</p>
                </div>
                <div class="flex gap-2">
                    <button type="button" x-on:click="rango = 6" class="md-btn" :class="rango === 6 ? 'md-btn-primary' : 'md-btn-soft'">6 meses</button>
                    <button type="button" x-on:click="rango = 12" class="md-btn" :class="rango === 12 ? 'md-btn-primary' : 'md-btn-soft'">12 meses</button>
                </div>
            </div>

            <div class="mt-5 flex h-60 items-end gap-2.5 border-b border-[var(--md-edge)] pb-2" x-on:mouseleave="mes = visible.length - 1">
                <template x-for="(m, i) in visible" :key="m.clave">
                    <button type="button" class="flex h-full min-w-0 flex-1 flex-col justify-end rounded-md px-0.5 hover:bg-[var(--md-paper-2)]" x-on:mouseenter="mes = i" x-on:focus="mes = i">
                        <span class="flex h-48 items-end justify-center gap-1">
                            <span class="w-[42%] max-w-5 rounded-t bg-[var(--md-slate-line)]" :style="`height:${Math.max(m.cargos ? 4 : 0, alto(m.cargos) / 180 * 100)}%`"></span>
                            <span class="w-[42%] max-w-5 rounded-t bg-[var(--md-slate)]" :style="`height:${Math.max(m.cobros ? 4 : 0, alto(m.cobros) / 180 * 100)}%`"></span>
                        </span>
                        <span class="mt-2 truncate text-center text-sm font-medium text-[var(--md-text-mute)]" x-text="m.label"></span>
                    </button>
                </template>
            </div>

            <div class="mt-4 flex flex-wrap items-baseline gap-x-8 gap-y-2" x-show="actual">
                <p class="font-display text-2xl capitalize text-[var(--md-text)]" x-text="actual.label"></p>
                <p class="text-[var(--md-text-mute)]">Cargos <span class="md-money text-lg text-[var(--md-text)]" x-text="plata(actual.cargos)"></span></p>
                <p class="text-[var(--md-text-mute)]">Cobros <span class="md-money text-lg text-[var(--md-text)]" x-text="plata(actual.cobros)"></span></p>
                <p class="text-sm text-[var(--md-text-mute)]">
                    <span class="mr-3"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--md-slate-line)]"></span> Cargos</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--md-slate)]"></span> Cobros</span>
                </p>
            </div>
        </div>

        <div class="md-panel mt-6 p-6">
            <p class="text-lg font-semibold text-[var(--md-text)]">Antigüedad de la deuda</p>
            <p class="text-[var(--md-text-mute)]">Tocá un tramo para ver el importe.</p>
            <div class="mt-4 flex h-11 overflow-hidden rounded-lg">
                <template x-for="t in tramos" :key="t.clave">
                    <button type="button" class="h-full min-w-2 transition hover:opacity-90" :style="`width:${t.pct}%; background:${t.color}`"
                            :title="t.texto" x-on:click="tramo = t.clave"></button>
                </template>
            </div>
            <p class="mt-3 text-lg font-medium text-[var(--md-text)]" x-text="tramoActivo"></p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="md-panel overflow-hidden">
                <div class="flex flex-wrap gap-2 border-b border-[var(--md-edge)] px-5 py-4">
                    <button type="button" x-on:click="nivel = 'todos'" class="md-btn" :class="nivel === 'todos' ? 'md-btn-primary' : 'md-btn-soft'">Todos</button>
                    <template x-for="n in listaNiveles" :key="n.clave">
                        <button type="button" x-on:click="nivel = n.clave" class="md-btn" :class="nivel === n.clave ? 'md-btn-primary' : 'md-btn-soft'">
                            <span x-text="n.texto"></span> · <span x-text="n.cant"></span>
                        </button>
                    </template>
                </div>
                <table class="w-full">
                    <template x-for="d in filtrados" :key="d.url">
                        <tr>
                            <td><a class="text-[1.05rem]" :href="d.url" x-text="d.nombre"></a></td>
                            <td class="text-[var(--md-text-mute)]" x-text="d.nivel.replace('_', ' ')"></td>
                            <td class="md-money text-right text-[1.05rem]" x-text="plata(d.saldo)"></td>
                        </tr>
                    </template>
                </table>
                <p class="px-5 py-4 text-[var(--md-text-mute)]" x-show="filtrados.length === 0">Nadie en ese estado con saldo.</p>
            </div>

            <div class="md-panel overflow-hidden">
                <div class="border-b border-[var(--md-edge)] px-5 py-4">
                    <p class="text-lg font-semibold text-[var(--md-text)]">Últimos pagos</p>
                </div>
                <table class="w-full">
                    @forelse ($ultimosPagos as $p)
                        <tr>
                            <td class="whitespace-nowrap">{{ $p->fecha->format('d/m/Y') }}</td>
                            <td>{{ $p->customer?->nombre ?? '—' }}</td>
                            <td class="md-money text-right">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-6 text-[var(--md-text-mute)]">Todavía no hay pagos.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>

        <details class="mt-8 text-[var(--md-text-mute)]">
            <summary class="cursor-pointer text-base font-medium text-[var(--md-text)]">Salud del sistema</summary>
            <ul class="mt-2 space-y-1 text-base">
                <li>Cuotas generadas en {{ $periodo }}: {{ $cuotasMes }}</li>
                <li>Webhooks sin procesar hace más de 30 minutos: {{ $webhooksConError }}</li>
                <li>Última conciliación:
                    @if ($ultimaConciliacion)
                        {{ $ultimaConciliacion->estado }} ({{ $ultimaConciliacion->created_at->format('d/m H:i') }})
                    @else
                        sin corridas
                    @endif
                </li>
            </ul>
        </details>
    </div>

    <script>
        function cobrosTablero() {
            const datos = JSON.parse(document.getElementById('cobros-datos').textContent);
            const nombres = { al_dia: 'Al día', pendiente: 'Pendiente', vencido: 'Vencido', por_suspender: 'Por suspender', moroso: 'Moroso', suspendido: 'Suspendido' };
            const tramoNombre = { '0_30': '0 a 30 días', '31_60': '31 a 60 días', '61_90': '61 a 90 días', mas_90: 'Más de 90 días' };
            const tramoColor = { '0_30': '#3F7A55', '31_60': '#7A92B0', '61_90': '#B8923F', mas_90: '#B84941' };
            return {
                serie: datos.serie,
                deudores: datos.deudores,
                rango: 12,
                mes: datos.serie.length - 1,
                nivel: 'todos',
                tramo: 'mas_90',
                get visible() { return this.rango === 6 ? this.serie.slice(-6) : this.serie; },
                get tope() { return Math.max(1, ...this.visible.flatMap(m => [m.cargos, m.cobros])); },
                alto(v) { return Math.round((v / this.tope) * 180); },
                get actual() { return this.visible[Math.min(this.mes, this.visible.length - 1)]; },
                plata(c) { return (c / 100).toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }); },
                get listaNiveles() {
                    return Object.entries(datos.niveles).map(([clave, cant]) => ({ clave, cant, texto: nombres[clave] || clave }));
                },
                get filtrados() {
                    return this.deudores.filter(d => this.nivel === 'todos' || d.nivel === this.nivel).slice(0, 20);
                },
                get tramos() {
                    const total = Object.values(datos.aging).reduce((a, b) => a + b, 0) || 1;
                    return Object.entries(datos.aging).map(([clave, v]) => ({
                        clave, v, pct: Math.max(v > 0 ? 2 : 0, v / total * 100), color: tramoColor[clave], texto: tramoNombre[clave],
                    }));
                },
                get tramoActivo() {
                    const t = this.tramos.find(x => x.clave === this.tramo);
                    return t ? t.texto + ' · ' + this.plata(t.v) : '';
                },
            };
        }
    </script>
@endsection
