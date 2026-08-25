@extends('layouts.app')

@section('titulo', 'Informe de ventas')

@section('contenido')
    @include('informes._nav')

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Desde</label>
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                   class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                   class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Vendedor</label>
            <select name="vendedor" class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
                <option value="">Todos</option>
                @foreach ($vendedores as $v)
                    <option value="{{ $v->id }}" @selected(($filtros['user_id'] ?? null) === $v->id)>{{ $v->name }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Sucursal</label>
            <select name="sucursal" class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
                <option value="">Todas</option>
                @foreach ($sucursales as $s)
                    <option value="{{ $s->id }}" @selected(($filtros['sucursal_id'] ?? null) === $s->id)>{{ $s->nombre }}</option>
                @endforeach
            </select>
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Medio de pago</label>
            <select name="medio_pago" class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
                <option value="">Todos</option>
                @foreach ($mediosPago as $m)
                    <option value="{{ $m->id }}" @selected(($filtros['medio_pago_id'] ?? null) === $m->id)>{{ $m->nombre }}</option>
                @endforeach
            </select>
        </div>
        <button class="h-[38px] rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}"
           class="inline-flex h-[38px] items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium hover:bg-slate-50">
            Exportar CSV
        </a>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-1">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total vendido</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($totales['total'], 2, ',', '.') }}</p>
            <p class="mt-1 text-xs {{ $totales['variacion_pct'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                {{ $totales['variacion_pct'] >= 0 ? '▲' : '▼' }}
                {{ number_format(abs($totales['variacion_pct']), 1, ',', '.') }}% vs período anterior
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ventas</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($totales['cantidad'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ticket promedio</p>
            <p class="mt-1 text-2xl font-bold">$ {{ number_format($totales['promedio'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clientes</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($totales['clientes'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Descuentos</p>
            <p class="mt-1 text-2xl font-bold text-red-600">$ {{ number_format($totales['descuentos'], 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Evolución diaria</h2>
            <canvas id="chartVentasDia" height="120"></canvas>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Medios de pago</h2>
            <canvas id="chartMedios" height="120"></canvas>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Por día</h2>
            <div class="max-h-80 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2 text-right">Tickets</th>
                            <th class="px-4 py-2 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($porDia as $dia)
                            <tr>
                                <td class="px-4 py-2">{{ \Carbon\Carbon::parse($dia->dia)->format('d/m/Y') }}</td>
                                <td class="px-4 py-2 text-right text-slate-500">{{ $dia->cantidad }}</td>
                                <td class="px-4 py-2 text-right font-semibold">$ {{ number_format((float) $dia->total, 2, ',', '.') }}</td>
                            </tr>
                        @empty
                            <tr><td colspan="3" class="px-4 py-8 text-center text-slate-400">Sin datos en el período.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Top productos</h2>
            <div class="max-h-80 overflow-auto">
                <table class="w-full text-sm">
                    <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Producto</th>
                            <th class="px-4 py-2 text-right">Cant.</th>
                            <th class="px-4 py-2 text-right">Venta</th>
                            <th class="px-4 py-2 text-right">Margen</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($topProductos as $p)
                            <tr>
                                <td class="px-4 py-2">
                                    <span class="font-medium">{{ $p->nombre }}</span>
                                    @if ($p->codigo)<span class="block text-xs text-slate-400">{{ $p->codigo }}</span>@endif
                                </td>
                                <td class="px-4 py-2 text-right text-slate-500">{{ rtrim(rtrim(number_format((float) $p->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-2 text-right font-semibold">$ {{ number_format((float) $p->venta, 2, ',', '.') }}</td>
                                <td class="px-4 py-2 text-right {{ $p->margen >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                    {{ number_format((float) $p->margen_pct, 1, ',', '.') }}%
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin datos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Por vendedor</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($porVendedor as $vendedor)
                        <tr>
                            <td class="px-5 py-2">{{ $vendedor->name }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ $vendedor->cantidad }} ventas</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $vendedor->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <h2 class="border-y border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Por sucursal</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($porSucursal as $suc)
                        <tr>
                            <td class="px-5 py-2">{{ $suc->nombre }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ $suc->cantidad }}</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $suc->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Top clientes</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($porCliente as $cli)
                        <tr>
                            <td class="px-5 py-2">{{ $cli->nombre }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ $cli->cantidad }}</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $cli->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
            <h2 class="border-y border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Por medio de pago</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($porMedio as $medio)
                        <tr>
                            <td class="px-5 py-2">{{ $medio->nombre }}</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $medio->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Sin datos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const dias = @json($porDia->map(fn ($d) => [
            'label' => \Carbon\Carbon::parse($d->dia)->format('d/m'),
            'total' => round((float) $d->total, 2),
        ])->values());
        const medios = @json($porMedio->map(fn ($m) => [
            'label' => $m->nombre,
            'total' => round((float) $m->total, 2),
        ])->values());

        if (dias.length) {
            new Chart(document.getElementById('chartVentasDia'), {
                type: 'line',
                data: {
                    labels: dias.map(d => d.label),
                    datasets: [{
                        label: 'Ventas $',
                        data: dias.map(d => d.total),
                        borderColor: '#4f46e5',
                        backgroundColor: 'rgba(79,70,229,.12)',
                        fill: true,
                        tension: .3,
                    }],
                },
                options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
            });
        }
        if (medios.length) {
            new Chart(document.getElementById('chartMedios'), {
                type: 'doughnut',
                data: {
                    labels: medios.map(m => m.label),
                    datasets: [{
                        data: medios.map(m => m.total),
                        backgroundColor: ['#4f46e5','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#64748b'],
                    }],
                },
                options: { plugins: { legend: { position: 'bottom' } } },
            });
        }
    </script>
    @endpush
@endsection
