@extends('layouts.app')

@section('titulo', 'Ventas')

@section('contenido')
    @php
        $idsFacturables = $ventas->filter(fn ($v) => $v->estado === 'completada' && ! $v->facturada)->pluck('id')->values();
        $puedeFacturarLote = auth()->user()->can('facturacion.emitir') && $emisores->isNotEmpty();
    @endphp

    <div @if($puedeFacturarLote) x-data="facturarLoteApp({
        emisorId: {{ $emisores->first()->id }},
        puntoVentaId: {{ $emisores->first()->puntosVenta->where('activo', true)->first()?->id ?? 'null' }},
        idsPagina: @js($idsFacturables),
        emisoresMeta: @js($emisores->map(fn ($e) => [
            'id' => $e->id,
            'puntos' => $e->puntosVenta->where('activo', true)->map(fn ($pv) => ['id' => $pv->id])->values(),
        ])),
    })" @endif>

        <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
            <form method="GET" class="flex flex-wrap items-center gap-2">
                <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                       class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Todas</option>
                    <option value="completada" {{ request('estado') === 'completada' ? 'selected' : '' }}>Completadas</option>
                    <option value="anulada" {{ request('estado') === 'anulada' ? 'selected' : '' }}>Anuladas</option>
                </select>
                <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
            </form>

            <div class="flex flex-wrap items-center gap-3">
                <div class="rounded-lg bg-emerald-50 px-4 py-2 text-sm">
                    <span class="text-emerald-600">Total del período:</span>
                    <span class="font-bold text-emerald-700">$ {{ number_format((float) $totalPeriodo, 2, ',', '.') }}</span>
                </div>
                @if ($puedeFacturarLote)
                    <button type="button" @click="modal = true" :disabled="! seleccionadas.length"
                            class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700 disabled:cursor-not-allowed disabled:bg-slate-300">
                        Facturar seleccionadas (<span x-text="seleccionadas.length"></span>)
                    </button>
                @endif
                @can('pos.vender')
                    <a href="{{ route('pos') }}"
                       class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Ir al POS
                    </a>
                @endcan
            </div>
        </div>

        @if ($puedeFacturarLote)
            <p class="mb-3 text-sm text-slate-600">
                Marcá ventas completadas sin facturar y elegí el <strong>CUIT emisor</strong> y punto de venta al autorizar en lote.
            </p>
        @endif

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="min-w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        @if ($puedeFacturarLote)
                            <th class="w-10 px-3 py-3">
                                <input type="checkbox" @change="togglePagina($event.target.checked)"
                                       title="Seleccionar página" class="rounded border-slate-300">
                            </th>
                        @endif
                        <th class="px-4 py-3">N°</th>
                        <th class="px-4 py-3">Fecha</th>
                        <th class="px-4 py-3">Cliente</th>
                        <th class="px-4 py-3">Vendedor</th>
                        <th class="px-4 py-3">Pago</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3 text-right">Total</th>
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($ventas as $v)
                        <tr class="hover:bg-slate-50 {{ $v->estado === 'anulada' ? 'opacity-60' : '' }}">
                            @if ($puedeFacturarLote)
                                <td class="px-3 py-3">
                                    @if ($v->estado === 'completada' && ! $v->facturada)
                                        <input type="checkbox" value="{{ $v->id }}"
                                               @change="toggleVenta({{ $v->id }}, $event.target.checked)"
                                               :checked="seleccionadas.includes({{ $v->id }})"
                                               class="rounded border-slate-300">
                                    @endif
                                </td>
                            @endif
                            <td class="px-4 py-3 font-mono font-semibold">#{{ str_pad($v->numero, 6, '0', STR_PAD_LEFT) }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $v->fecha->format('d/m/Y H:i') }}</td>
                            <td class="px-4 py-3">{{ $v->cliente?->nombre ?? 'Consumidor final' }}</td>
                            <td class="px-4 py-3 text-slate-600">{{ $v->vendedor->name }}</td>
                            <td class="px-4 py-3 text-xs text-slate-600">
                                {{ $v->pagos->map(fn ($p) => $p->medioPago->nombre)->unique()->implode(', ') }}
                            </td>
                            <td class="px-4 py-3">
                                @if ($v->estado === 'completada')
                                    @if ($v->facturada)
                                        <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">Facturada</span>
                                    @else
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Completada</span>
                                    @endif
                                @else
                                    <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Anulada</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $v->total, 2, ',', '.') }}</td>
                            <td class="px-4 py-3 text-right">
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('ventas.show', $v) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Ver</a>
                                    <a href="{{ route('ventas.ticket', $v) }}" target="_blank"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Ticket</a>
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="{{ $puedeFacturarLote ? 9 : 8 }}"
                                class="px-4 py-8 text-center text-slate-400">No hay ventas en el período.</td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="mt-4">{{ $ventas->links() }}</div>

        @if ($puedeFacturarLote)
            <div x-show="modal" x-cloak class="fixed inset-0 z-50 flex items-center justify-center bg-slate-900/50 p-4">
                <div class="w-full max-w-md rounded-2xl bg-white p-6 shadow-xl" @click.outside="modal = false">
                    <h2 class="text-lg font-bold text-slate-900">Facturar ventas por lote</h2>
                    <p class="mt-1 text-sm text-slate-500">
                        Se van a autorizar <strong x-text="seleccionadas.length"></strong> venta(s) ante AFIP.
                    </p>

                    <form method="POST" action="{{ route('facturacion.facturar-lote') }}" class="mt-4 space-y-3">
                        @csrf
                        <template x-for="id in seleccionadas" :key="id">
                            <input type="hidden" name="venta_ids[]" :value="id">
                        </template>

                        @include('facturacion.partials.selector-emisor', ['emisores' => $emisores])

                        <div class="flex gap-3 pt-2">
                            <button type="button" @click="modal = false"
                                    class="flex-1 rounded-lg border border-slate-300 py-2.5 text-sm font-medium hover:bg-slate-50">
                                Cancelar
                            </button>
                            <button type="submit"
                                    class="flex-1 rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700">
                                Solicitar CAE
                            </button>
                        </div>
                    </form>
                </div>
            </div>
        @endif
    </div>

    @if ($puedeFacturarLote)
        <script>
            function facturarLoteApp(config) {
                return {
                    seleccionadas: [],
                    modal: false,
                    emisorId: config.emisorId,
                    puntoVentaId: config.puntoVentaId,
                    idsPagina: config.idsPagina.map(Number),
                    emisoresMeta: config.emisoresMeta,
                    onEmisorChange() {
                        const e = this.emisoresMeta.find(x => Number(x.id) === Number(this.emisorId));
                        this.puntoVentaId = e?.puntos?.[0]?.id ?? null;
                    },
                    toggleVenta(id, checked) {
                        id = Number(id);
                        if (checked) {
                            if (! this.seleccionadas.includes(id)) this.seleccionadas.push(id);
                        } else {
                            this.seleccionadas = this.seleccionadas.filter(x => x !== id);
                        }
                    },
                    togglePagina(checked) {
                        if (checked) {
                            this.idsPagina.forEach(id => {
                                if (! this.seleccionadas.includes(id)) this.seleccionadas.push(id);
                            });
                        } else {
                            this.seleccionadas = this.seleccionadas.filter(id => ! this.idsPagina.includes(id));
                        }
                    },
                };
            }
        </script>
    @endif
@endsection
