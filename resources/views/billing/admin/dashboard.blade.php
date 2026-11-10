@extends('billing.admin.layout')
@section('titulo', 'Tablero')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    @php($tableroJson = json_encode(['serie' => $serie, 'deudores' => $deudores, 'aging' => $aging, 'niveles' => $niveles], JSON_UNESCAPED_UNICODE))
    <script type="application/json" id="cobros-datos">{!! $tableroJson !!}</script>

    <div x-data="cobrosTablero()" x-cloak>
        <div class="mb-6">
            <h1 class="font-display text-3xl font-medium tracking-tight text-white">Tablero</h1>
            <p class="mt-2 max-w-4xl text-lg text-[var(--md-ink-mute)]">
                Deuda total <span class="md-money text-white">{{ Money::formato($deudaTotal) }}</span>.
                En {{ $periodo }} se facturó {{ Money::formato($facturado) }} y entraron {{ Money::formato($cobrado) }}
                @if ($tasa !== null)({{ $tasa }} %)@endif.
                Abono mensual activo: {{ Money::formato($mrr) }}.
            </p>
        </div>

        <div class="md-panel p-6">
            <div class="flex flex-wrap items-end justify-between gap-4">
                <p class="text-[var(--md-text-mute)]">
                    <span class="mr-4"><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--md-slate)]"></span> Cargos</span>
                    <span><span class="inline-block h-2.5 w-2.5 rounded-sm bg-[var(--md-slate-deep)]"></span> Cobros</span>
                </p>
                <div class="flex gap-2">
                    <button type="button" x-on:click="rango = 6" class="md-btn" :class="rango === 6 ? 'md-btn-primary' : 'md-btn-ghost'">6 meses</button>
                    <button type="button" x-on:click="rango = 12" class="md-btn" :class="rango === 12 ? 'md-btn-primary' : 'md-btn-ghost'">12 meses</button>
                </div>
            </div>

            <div class="mt-4 flex h-56 items-end gap-2" x-on:mouseleave="mes = visible.length - 1">
                <template x-for="(m, i) in visible" :key="m.clave">
                    <button type="button" class="flex h-full min-w-0 flex-1 flex-col justify-end" x-on:mouseenter="mes = i" x-on:focus="mes = i">
                        <span class="flex h-48 items-end gap-1">
                            <span class="w-1/2 rounded-t bg-[var(--md-slate)]" :style="`height:${Math.max(m.cargos ? 3 : 0, alto(m.cargos) / 180 * 100)}%`"></span>
                            <span class="w-1/2 rounded-t bg-[var(--md-slate-deep)]" :style="`height:${Math.max(m.cobros ? 3 : 0, alto(m.cobros) / 180 * 100)}%`"></span>
                        </span>
                        <span class="mt-2 truncate text-center text-sm font-medium text-[var(--md-text-mute)]" x-text="m.label"></span>
                    </button>
                </template>
            </div>

            <p class="mt-4 font-display text-2xl text-[var(--md-text)]" x-show="actual">
                <span class="capitalize" x-text="actual.label"></span>
                <span class="ml-5 text-lg text-[var(--md-text-mute)]">cargos <span class="md-money text-[var(--md-text)]" x-text="plata(actual.cargos)"></span></span>
                <span class="ml-5 text-lg text-[var(--md-text-mute)]">cobros <span class="md-money text-[var(--md-text)]" x-text="plata(actual.cobros)"></span></span>
            </p>
        </div>

        <div class="md-panel mt-6 p-6">
            <p class="text-lg font-semibold text-[var(--md-text)]">Antigüedad de la deuda</p>
            <p class="text-[var(--md-text-mute)]">Tocá un tramo para ver el importe.</p>
            <div class="mt-3 flex h-10 overflow-hidden rounded-lg">
                <template x-for="t in tramos" :key="t.clave">
                    <button type="button" class="h-full min-w-2" :style="`width:${t.pct}%; background:${t.color}`"
                            :title="t.texto" x-on:click="tramo = t.clave"></button>
                </template>
            </div>
            <p class="mt-3 text-lg font-medium text-[var(--md-text)]" x-text="tramoActivo"></p>
        </div>

        <div class="mt-6 grid gap-6 lg:grid-cols-2">
            <div class="md-panel p-5">
                <div class="mb-3 flex flex-wrap gap-2">
                    <button type="button" x-on:click="nivel = 'todos'" class="md-btn" :class="nivel === 'todos' ? 'md-btn-primary' : 'md-btn-ghost'">Todos</button>
                    <template x-for="n in listaNiveles" :key="n.clave">
                        <button type="button" x-on:click="nivel = n.clave" class="md-btn" :class="nivel === n.clave ? 'md-btn-primary' : 'md-btn-ghost'">
                            <span x-text="n.texto"></span> <span x-text="n.cant"></span>
                        </button>
                    </template>
                </div>
                <table class="w-full">
                    <template x-for="d in filtrados" :key="d.url">
                        <tr>
                            <td class="!py-3"><a class="text-base" :href="d.url" x-text="d.nombre"></a></td>
                            <td class="!py-3 text-[var(--md-text-mute)]" x-text="d.nivel.replace('_', ' ')"></td>
                            <td class="md-money !py-3 text-right text-base" x-text="plata(d.saldo)"></td>
                        </tr>
                    </template>
                </table>
                <p class="mt-3 text-[var(--md-text-mute)]" x-show="filtrados.length === 0">Nadie en ese estado con saldo.</p>
            </div>

            <div class="md-panel p-5">
                <p class="mb-2 text-lg font-semibold text-[var(--md-text)]">Últimos pagos</p>
                <table class="w-full">
                    @forelse ($ultimosPagos as $p)
                        <tr>
                            <td class="!py-3 whitespace-nowrap">{{ $p->fecha->format('d/m/Y') }}</td>
                            <td class="!py-3">{{ $p->customer?->nombre ?? '—' }}</td>
                            <td class="md-money !py-3 text-right">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-4 text-[var(--md-text-mute)]">Todavía no hay pagos.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>

        <details class="mt-6 text-[var(--md-ink-mute)]">
            <summary class="cursor-pointer text-base text-white/80">Salud del sistema</summary>
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
            const tramoColor = { '0_30': '#4F7A5C', '31_60': '#7A92B0', '61_90': '#B8923F', mas_90: '#B84941' };
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
