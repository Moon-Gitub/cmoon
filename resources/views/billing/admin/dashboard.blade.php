@extends('billing.admin.layout')
@section('titulo', 'Tablero')
@php
    use App\Billing\Support\Money;
    $morosos = $niveles['moroso'] ?? 0;
    $alDia = $niveles['al_dia'] ?? 0;
    $conDeuda = $morosos + ($niveles['vencido'] ?? 0) + ($niveles['por_suspender'] ?? 0) + ($niveles['pendiente'] ?? 0);
@endphp

@section('contenido')
    @php($tableroJson = json_encode(['serie' => $serie, 'deudores' => $deudores, 'aging' => $aging, 'niveles' => $niveles], JSON_UNESCAPED_UNICODE))
    <script type="application/json" id="cobros-datos">{!! $tableroJson !!}</script>

    <div x-data="cobrosTablero()" x-cloak class="space-y-6">
        <div class="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 class="md-dash-title">Tablero de cobranza</h1>
                <p class="md-dash-sub mt-1">Moon Cobros · período {{ $periodo }}</p>
            </div>
            <div class="flex items-center gap-2 rounded-full border border-white/15 bg-white/5 px-4 py-2 text-sm text-[#c5d3e3]">
                <span class="h-2 w-2 animate-pulse rounded-full bg-[#5bd48a]"></span>
                Datos en vivo del libro
            </div>
        </div>

        <div class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div class="md-dash-kpi">
                <div class="kpi-label">Deuda total</div>
                <div class="kpi-value">{{ Money::formato($deudaTotal) }}</div>
                <div class="kpi-hint">{{ $conDeuda }} cuentas con saldo</div>
            </div>
            <div class="md-dash-kpi">
                <div class="kpi-label">Facturado</div>
                <div class="kpi-value">{{ Money::formato($facturado) }}</div>
                <div class="kpi-hint">Mes {{ $periodo }}</div>
            </div>
            <div class="md-dash-kpi">
                <div class="kpi-label">Cobrado</div>
                <div class="kpi-value">{{ Money::formato($cobrado) }}</div>
                <div class="kpi-hint">@if ($tasa !== null)Tasa de cobro {{ $tasa }}% @else Sin facturación en el mes @endif</div>
            </div>
            <div class="md-dash-kpi">
                <div class="kpi-label">Cartera</div>
                <div class="kpi-value">{{ $morosos }} <span class="text-lg text-[#a8bdd4]">morosos</span></div>
                <div class="kpi-hint">MRR {{ Money::formato($mrr) }} · {{ $alDia }} al día</div>
            </div>
        </div>

        <div class="grid gap-6 xl:grid-cols-3">
            <div class="md-dash-card p-6 xl:col-span-2">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="text-lg font-semibold text-white">Cargos, cobros y tasa</h2>
                        <p class="text-sm text-[#a8bdd4]">Últimos <span x-text="rango"></span> meses</p>
                    </div>
                    <div class="flex gap-2">
                        <button type="button" x-on:click="rango = 6" class="md-btn md-btn-soft text-sm" :class="rango === 6 && '!bg-white/20'">6 m</button>
                        <button type="button" x-on:click="rango = 12" class="md-btn md-btn-soft text-sm" :class="rango === 12 && '!bg-white/20'">12 m</button>
                    </div>
                </div>

                <svg class="mt-5 w-full select-none" viewBox="0 0 760 300" role="img" aria-label="Gráfico mensual" x-on:mouseleave="mes = visible.length - 1">
                    <template x-for="(tick, ti) in gridTicks" :key="'g'+ti">
                        <line :x1="48" :y1="tick.y" :x2="740" :y2="tick.y" stroke="rgba(255,255,255,0.08)" stroke-width="1"/>
                        <text :x="40" :y="tick.y + 4" text-anchor="end" fill="#8fa6c4" font-size="11" x-text="tick.label"></text>
                    </template>
                    <template x-for="(m, i) in visible" :key="m.clave">
                        <g :transform="`translate(${barX(i)}, 0)`" class="cursor-pointer" x-on:mouseenter="mes = i">
                            <rect :x="0" :y="0" :width="barW" height="260" fill="transparent"/>
                            <rect :x="barW * 0.12" :y="barY(m.cargos)" :width="barW * 0.32" :height="barH(m.cargos)" rx="3" fill="rgba(255,255,255,0.22)"/>
                            <rect :x="barW * 0.52" :y="barY(m.cobros)" :width="barW * 0.32" :height="barH(m.cobros)" rx="3" fill="#7eb8ff"/>
                            <circle :cx="barW * 0.7" :cy="tasaY(m)" r="4" fill="#5bd48a"/>
                            <text :x="barW / 2" y="278" text-anchor="middle" fill="#a8bdd4" font-size="12" x-text="m.label"></text>
                        </g>
                    </template>
                    <polyline fill="none" stroke="#5bd48a" stroke-width="2" :points="tasaLine"/>
                </svg>

                <div class="mt-4 flex flex-wrap items-center gap-6 border-t border-white/10 pt-4 text-sm">
                    <span class="text-white capitalize" x-text="actual?.label"></span>
                    <span class="text-[#a8bdd4]">Cargos <strong class="md-money text-white" x-text="plata(actual.cargos)"></strong></span>
                    <span class="text-[#a8bdd4]">Cobros <strong class="md-money text-white" x-text="plata(actual.cobros)"></strong></span>
                    <span class="text-[#a8bdd4]">Tasa <strong class="text-[#5bd48a]" x-text="tasaMes(actual)"></strong></span>
                </div>
            </div>

            <div class="md-dash-card p-6">
                <h2 class="text-lg font-semibold text-white">Estado de clientes</h2>
                <p class="text-sm text-[#a8bdd4]">Distribución de la cartera activa</p>
                <div class="mt-6 flex flex-col items-center">
                    <svg viewBox="0 0 200 200" class="h-44 w-44">
                        <template x-for="(slice, si) in donut" :key="slice.clave">
                            <path :d="slice.d" :fill="slice.color" stroke="#354a68" stroke-width="1"/>
                        </template>
                        <circle cx="100" cy="100" r="42" fill="#354a68"/>
                        <text x="100" y="96" text-anchor="middle" fill="#fff" font-size="22" font-weight="600" x-text="donutTotal"></text>
                        <text x="100" y="114" text-anchor="middle" fill="#a8bdd4" font-size="11">clientes</text>
                    </svg>
                    <ul class="mt-4 w-full space-y-2 text-sm">
                        <template x-for="slice in donut" :key="'leg-'+slice.clave">
                            <li class="flex items-center justify-between gap-2">
                                <span class="flex items-center gap-2 text-[#d0dcec]">
                                    <span class="h-2.5 w-2.5 rounded-sm" :style="`background:${slice.color}`"></span>
                                    <span x-text="slice.texto"></span>
                                </span>
                                <span class="md-money text-white" x-text="slice.cant"></span>
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </div>

        <div class="md-dash-card p-6">
            <h2 class="text-lg font-semibold text-white">Antigüedad de la deuda</h2>
            <div class="mt-4 grid gap-3 sm:grid-cols-4">
                <template x-for="t in tramos" :key="t.clave">
                    <button type="button" class="rounded-lg border border-white/10 bg-black/15 p-4 text-left transition hover:border-white/25"
                            x-on:click="tramo = t.clave" :class="tramo === t.clave && 'ring-2 ring-white/30'">
                        <div class="text-xs font-semibold uppercase tracking-wide text-[#a8bdd4]" x-text="t.texto"></div>
                        <div class="md-money mt-2 text-xl text-white" x-text="plata(t.v)"></div>
                        <div class="mt-2 h-1.5 overflow-hidden rounded-full bg-white/10">
                            <span class="block h-full rounded-full" :style="`width:${t.pct}%; background:${t.color}`"></span>
                        </div>
                    </button>
                </template>
            </div>
        </div>

        <div class="grid gap-6 lg:grid-cols-2">
            <div class="md-dash-card overflow-hidden">
                <div class="flex flex-wrap gap-2 border-b border-white/10 px-5 py-4">
                    <button type="button" x-on:click="nivel = 'todos'" class="md-btn md-btn-soft text-sm" :class="nivel === 'todos' && '!bg-white/20'">Todos</button>
                    <template x-for="n in listaNiveles" :key="n.clave">
                        <button type="button" x-on:click="nivel = n.clave" class="md-btn md-btn-soft text-sm" :class="nivel === n.clave && '!bg-white/20'">
                            <span x-text="n.texto"></span> · <span x-text="n.cant"></span>
                        </button>
                    </template>
                </div>
                <table class="w-full">
                    <thead><tr><th>Cliente</th><th>Estado</th><th class="text-right">Saldo</th></tr></thead>
                    <tbody>
                    <template x-for="d in filtrados" :key="d.url">
                        <tr>
                            <td><a class="!text-[1.05rem]" :href="d.url" x-text="d.nombre"></a></td>
                            <td><span class="md-badge md-badge-mute capitalize" x-text="d.nivel.replace('_', ' ')"></span></td>
                            <td class="md-money text-right text-[1.05rem]" x-text="plata(d.saldo)"></td>
                        </tr>
                    </template>
                    </tbody>
                </table>
                <p class="px-5 py-4 text-[#a8bdd4]" x-show="filtrados.length === 0">Nadie en ese estado con saldo.</p>
            </div>

            <div class="md-dash-card overflow-hidden">
                <div class="border-b border-white/10 px-5 py-4">
                    <h2 class="text-lg font-semibold text-white">Últimos pagos</h2>
                </div>
                <table class="w-full">
                    <thead><tr><th>Fecha</th><th>Cliente</th><th class="text-right">Importe</th></tr></thead>
                    <tbody>
                    @forelse ($ultimosPagos as $p)
                        <tr>
                            <td class="whitespace-nowrap">{{ $p->fecha->format('d/m/Y') }}</td>
                            <td>{{ $p->customer?->nombre ?? '—' }}</td>
                            <td class="md-money text-right text-[#5bd48a]">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-6 text-[#a8bdd4]">Todavía no hay pagos.</td></tr>
                    @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <details class="text-[#a8bdd4]">
            <summary class="cursor-pointer text-base text-white/80">Salud del sistema</summary>
            <ul class="mt-2 space-y-1 text-base">
                <li>Cuotas generadas en {{ $periodo }}: {{ $cuotasMes }}</li>
                <li>Webhooks sin procesar (+30 min): {{ $webhooksConError }}</li>
                <li>Última conciliación:
                    @if ($ultimaConciliacion)
                        {{ $ultimaConciliacion->estado }} ({{ $ultimaConciliacion->created_at->format('d/m H:i') }})
                    @else sin corridas @endif
                </li>
            </ul>
        </details>
    </div>

    <script>
        function cobrosTablero() {
            const datos = JSON.parse(document.getElementById('cobros-datos').textContent);
            const nombres = { al_dia: 'Al día', pendiente: 'Pendiente', vencido: 'Vencido', por_suspender: 'Por suspender', moroso: 'Moroso', suspendido: 'Suspendido' };
            const nivelColor = { al_dia: '#5bd48a', pendiente: '#7eb8ff', vencido: '#e0b24f', por_suspender: '#e09850', moroso: '#e07068', suspendido: '#c94b44' };
            const tramoNombre = { '0_30': '0–30 días', '31_60': '31–60 días', '61_90': '61–90 días', mas_90: 'Más de 90 días' };
            const tramoColor = { '0_30': '#5bd48a', '31_60': '#7eb8ff', '61_90': '#e0b24f', mas_90: '#e07068' };
            const chartTop = 28, chartBottom = 248, chartH = chartBottom - chartTop;
            const chartLeft = 56, chartWidth = 680;

            return {
                serie: datos.serie,
                deudores: datos.deudores,
                rango: 12,
                mes: datos.serie.length - 1,
                nivel: 'todos',
                tramo: 'mas_90',
                get visible() { return this.rango === 6 ? this.serie.slice(-6) : this.serie; },
                get tope() { return Math.max(1, ...this.visible.flatMap(m => [m.cargos, m.cobros])); },
                get barW() { return chartWidth / Math.max(this.visible.length, 1) * 0.85; },
                barX(i) { return chartLeft + (chartWidth / this.visible.length) * i + (chartWidth / this.visible.length - this.barW) / 2; },
                barH(v) { return Math.max(v ? 3 : 0, (v / this.tope) * chartH); },
                barY(v) { return chartBottom - this.barH(v); },
                tasaY(m) {
                    const t = m.cargos > 0 ? Math.min(100, m.cobros / m.cargos * 100) : 0;
                    return chartBottom - (t / 100) * chartH;
                },
                tasaMes(m) { return !m || !m.cargos ? '—' : Math.round(m.cobros / m.cargos * 100) + '%'; },
                get actual() { return this.visible[Math.min(this.mes, this.visible.length - 1)]; },
                plata(c) { return (c / 100).toLocaleString('es-AR', { style: 'currency', currency: 'ARS' }); },
                get gridTicks() {
                    const n = 4;
                    const out = [];
                    for (let i = 0; i <= n; i++) {
                        const v = (this.tope / n) * (n - i);
                        out.push({ y: chartTop + (chartH / n) * i, label: this.plata(v).replace('ARS', '').trim() });
                    }
                    return out;
                },
                get tasaLine() {
                    return this.visible.map((m, i) => {
                        const x = this.barX(i) + this.barW * 0.7;
                        const y = this.tasaY(m);
                        return `${x},${y}`;
                    }).join(' ');
                },
                get listaNiveles() {
                    return Object.entries(datos.niveles).map(([clave, cant]) => ({ clave, cant, texto: nombres[clave] || clave }));
                },
                get filtrados() {
                    return this.deudores.filter(d => this.nivel === 'todos' || d.nivel === this.nivel).slice(0, 15);
                },
                get tramos() {
                    const total = Object.values(datos.aging).reduce((a, b) => a + b, 0) || 1;
                    return Object.entries(datos.aging).map(([clave, v]) => ({
                        clave, v, pct: Math.max(v > 0 ? 4 : 0, v / total * 100), color: tramoColor[clave], texto: tramoNombre[clave],
                    }));
                },
                get donutTotal() { return Object.values(datos.niveles).reduce((a, b) => a + b, 0); },
                get donut() {
                    const entries = Object.entries(datos.niveles).filter(([, c]) => c > 0);
                    const total = entries.reduce((s, [, c]) => s + c, 0) || 1;
                    let angle = -Math.PI / 2;
                    const r = 78, cx = 100, cy = 100;
                    return entries.map(([clave, cant]) => {
                        const sweep = (cant / total) * Math.PI * 2;
                        const x1 = cx + r * Math.cos(angle);
                        const y1 = cy + r * Math.sin(angle);
                        angle += sweep;
                        const x2 = cx + r * Math.cos(angle);
                        const y2 = cy + r * Math.sin(angle);
                        const large = sweep > Math.PI ? 1 : 0;
                        const d = `M ${cx} ${cy} L ${x1} ${y1} A ${r} ${r} 0 ${large} 1 ${x2} ${y2} Z`;
                        return { clave, cant, texto: nombres[clave] || clave, color: nivelColor[clave] || '#8fa6c4', d };
                    });
                },
            };
        }
    </script>
@endsection
