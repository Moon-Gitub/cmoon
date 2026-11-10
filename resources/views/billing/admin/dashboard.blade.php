@extends('billing.admin.layout')
@section('titulo', 'Tablero')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    @php($tableroJson = json_encode(['serie' => $serie, 'deudores' => $deudores, 'aging' => $aging, 'niveles' => $niveles], JSON_UNESCAPED_UNICODE))
    <script type="application/json" id="cobros-datos">{!! $tableroJson !!}</script>

    <div x-data="cobrosTablero()" x-cloak>
        <p class="max-w-3xl text-lg leading-snug">
            Deuda total <span class="text-[#76b900]">{{ Money::formato($deudaTotal) }}</span>.
            En {{ $periodo }} se facturó {{ Money::formato($facturado) }} y entraron {{ Money::formato($cobrado) }}
            @if ($tasa !== null)({{ $tasa }} %)@endif.
            El abono mensual de quienes están activos suma {{ Money::formato($mrr) }}.
        </p>

        <div class="mt-8 flex items-end justify-between gap-4">
            <p class="text-sm text-[#a3a3a3]">
                <span class="mr-4"><span class="inline-block h-2 w-2 bg-[#3a3a3a]"></span> Cargos</span>
                <span><span class="inline-block h-2 w-2 bg-[#76b900]"></span> Cobros</span>
            </p>
            <div class="flex gap-2 text-sm">
                <button type="button" x-on:click="rango = 6" :class="rango === 6 ? 'text-[#76b900]' : 'text-[#a3a3a3]'" class="hover:text-white">6 meses</button>
                <button type="button" x-on:click="rango = 12" :class="rango === 12 ? 'text-[#76b900]' : 'text-[#a3a3a3]'" class="hover:text-white">12 meses</button>
            </div>
        </div>

        <div class="mt-3 flex h-52 items-end gap-1" x-on:mouseleave="mes = visible.length - 1">
            <template x-for="(m, i) in visible" :key="m.clave">
                <button type="button" class="flex h-full min-w-0 flex-1 flex-col justify-end" x-on:mouseenter="mes = i" x-on:focus="mes = i">
                    <span class="flex h-44 items-end gap-0.5">
                        <span class="w-1/2 bg-[#3a3a3a]" :style="`height:${Math.max(m.cargos ? 2 : 0, alto(m.cargos) / 180 * 100)}%`"></span>
                        <span class="w-1/2 bg-[#76b900]" :style="`height:${Math.max(m.cobros ? 2 : 0, alto(m.cobros) / 180 * 100)}%`"></span>
                    </span>
                    <span class="mt-1 truncate text-center text-[11px] text-[#a3a3a3]" x-text="m.label"></span>
                </button>
            </template>
        </div>

        <p class="mt-2 text-2xl" x-show="actual">
            <span x-text="actual.label"></span>
            <span class="ml-4 text-base text-[#a3a3a3]">cargos <span class="text-white" x-text="plata(actual.cargos)"></span></span>
            <span class="ml-4 text-base text-[#a3a3a3]">cobros <span class="text-[#76b900]" x-text="plata(actual.cobros)"></span></span>
        </p>

        <div class="mt-10">
            <p class="text-sm text-[#a3a3a3]">Antigüedad de la deuda. Tocá un tramo.</p>
            <div class="mt-2 flex h-8 overflow-hidden">
                <template x-for="t in tramos" :key="t.clave">
                    <button type="button" class="h-full min-w-2" :style="`width:${t.pct}%; background:${t.color}`"
                            :title="t.texto" x-on:click="tramo = t.clave"></button>
                </template>
            </div>
            <p class="mt-2 text-sm" x-text="tramoActivo"></p>
        </div>

        <div class="mt-8 flex flex-wrap gap-x-4 gap-y-2 text-sm">
            <button type="button" x-on:click="nivel = 'todos'" :class="nivel === 'todos' ? 'text-[#76b900]' : 'text-[#a3a3a3]'" class="hover:text-white">Todos</button>
            <template x-for="n in listaNiveles" :key="n.clave">
                <button type="button" x-on:click="nivel = n.clave" :class="nivel === n.clave ? 'text-[#76b900]' : 'text-[#a3a3a3]'" class="hover:text-white">
                    <span x-text="n.texto"></span> <span x-text="n.cant"></span>
                </button>
            </template>
        </div>

        <div class="mt-4 grid gap-10 lg:grid-cols-2">
            <div>
                <table class="w-full text-sm">
                    <template x-for="d in filtrados" :key="d.url">
                        <tr class="border-t border-[#2a2a2a]">
                            <td class="py-2"><a class="hover:text-[#76b900]" :href="d.url" x-text="d.nombre"></a></td>
                            <td class="text-[#a3a3a3]" x-text="d.nivel.replace('_', ' ')"></td>
                            <td class="py-2 text-right" x-text="plata(d.saldo)"></td>
                        </tr>
                    </template>
                </table>
                <p class="mt-2 text-sm text-[#a3a3a3]" x-show="filtrados.length === 0">Nadie en ese estado con saldo.</p>
            </div>
            <div>
                <p class="text-sm text-[#a3a3a3]">Últimos pagos</p>
                <table class="mt-2 w-full text-sm">
                    @forelse ($ultimosPagos as $p)
                        <tr class="border-t border-[#2a2a2a]">
                            <td class="py-2">{{ $p->fecha->format('d/m/Y') }}</td>
                            <td>{{ $p->customer?->nombre ?? '—' }}</td>
                            <td class="text-right">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                        </tr>
                    @empty
                        <tr><td class="py-2 text-[#a3a3a3]">Todavía no hay pagos.</td></tr>
                    @endforelse
                </table>
            </div>
        </div>

        <details class="mt-10 text-sm text-[#a3a3a3]">
            <summary class="cursor-pointer">Salud del sistema</summary>
            <ul class="mt-2 space-y-1">
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
            const tramoColor = { '0_30': '#76b900', '31_60': '#5c8f00', '61_90': '#c4a35a', mas_90: '#e85d4c' };
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
