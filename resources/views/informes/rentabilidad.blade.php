@extends('layouts.app')

@section('titulo', 'Rentabilidad')

@section('contenido')
    @include('informes._nav')

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Desde</label>
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}" class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}" class="h-[38px] rounded-lg border border-slate-300 px-3 text-sm">
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
        <button class="h-[38px] rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}"
           class="inline-flex h-[38px] items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium hover:bg-slate-50">Exportar CSV</a>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-3 xl:grid-cols-6">
        <div class="rounded-xl border-l-4 border-l-indigo-500 border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Ventas</p>
            <p class="mt-1 text-xl font-bold">$ {{ number_format($data['venta'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border-l-4 border-l-slate-400 border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Costos</p>
            <p class="mt-1 text-xl font-bold">$ {{ number_format($data['costo'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border-l-4 border-l-emerald-500 border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Margen bruto</p>
            <p class="mt-1 text-xl font-bold text-emerald-600">$ {{ number_format($data['margen_bruto'], 2, ',', '.') }}</p>
            <p class="text-xs text-slate-500">{{ number_format($data['margen_pct'], 1, ',', '.') }}% · {{ $data['nivel'] }}</p>
        </div>
        <div class="rounded-xl border-l-4 border-l-amber-500 border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Gastos de caja</p>
            <p class="mt-1 text-xl font-bold text-amber-600">$ {{ number_format($data['gastos_caja'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm xl:col-span-2 {{ $data['margen_neto'] >= 0 ? 'border-l-4 border-l-emerald-500' : 'border-l-4 border-l-red-500' }}">
            <p class="text-xs font-semibold uppercase text-slate-500">Rentabilidad neta</p>
            <p class="mt-1 text-2xl font-bold {{ $data['margen_neto'] >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                $ {{ number_format($data['margen_neto'], 2, ',', '.') }}
            </p>
            <p class="text-xs text-slate-500">Margen bruto − egresos de caja del período</p>
        </div>
    </div>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Distribución</h2>
            <canvas id="chartRent" height="140"></canvas>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase text-slate-500">Top clientes</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($data['por_cliente'] as $c)
                        <tr>
                            <td class="px-5 py-2">{{ $c->nombre }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ $c->cantidad }}</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $c->total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase text-slate-500">Top 20 productos por margen</h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3 text-right">Cant.</th>
                    <th class="px-4 py-3 text-right">Costo</th>
                    <th class="px-4 py-3 text-right">Venta</th>
                    <th class="px-4 py-3 text-right">Margen</th>
                    <th class="px-4 py-3 text-right">%</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($data['top_productos'] as $p)
                    <tr>
                        <td class="px-4 py-2">
                            <span class="font-medium">{{ $p->nombre }}</span>
                            @if($p->codigo)<span class="block text-xs text-slate-400">{{ $p->codigo }}</span>@endif
                        </td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format((float) $p->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-2 text-right text-slate-600">$ {{ number_format((float) $p->costo, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right font-semibold">$ {{ number_format((float) $p->venta, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right {{ $p->margen >= 0 ? 'text-emerald-600' : 'text-red-600' }}">$ {{ number_format((float) $p->margen, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $p->margen_pct, 1, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">Sin datos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        new Chart(document.getElementById('chartRent'), {
            type: 'doughnut',
            data: {
                labels: ['Costo', 'Margen bruto', 'Gastos caja'],
                datasets: [{
                    data: [
                        {{ round($data['costo'], 2) }},
                        {{ round(max(0, $data['margen_bruto']), 2) }},
                        {{ round($data['gastos_caja'], 2) }},
                    ],
                    backgroundColor: ['#94a3b8', '#10b981', '#f59e0b'],
                }],
            },
            options: { plugins: { legend: { position: 'bottom' } } },
        });
    </script>
    @endpush
@endsection
