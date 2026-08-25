@extends('layouts.app')

@section('titulo', 'Productos vendidos')

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

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Artículos</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($resumen['items'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Unidades</p>
            <p class="mt-1 text-2xl font-bold">{{ rtrim(rtrim(number_format($resumen['unidades'], 3, ',', '.'), '0'), ',') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Costo</p>
            <p class="mt-1 text-2xl font-bold">$ {{ number_format($resumen['costo'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Venta</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($resumen['venta'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Margen</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">$ {{ number_format($resumen['margen'], 2, ',', '.') }}</p>
            <p class="text-xs text-slate-500">{{ number_format($resumen['margen_pct'], 1, ',', '.') }}%</p>
        </div>
    </div>

    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Top 10 por venta</h2>
            <canvas id="chartProd" height="140"></canvas>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm text-sm text-slate-600">
            <h2 class="mb-2 text-sm font-semibold uppercase text-slate-500">Cómo se calcula</h2>
            <p>El <strong>costo</strong> usa el precio de compra actual del producto × cantidad vendida (devoluciones restan). El margen es venta − costo.</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Costo</th>
                    <th class="px-4 py-3 text-right">Venta</th>
                    <th class="px-4 py-3 text-right">Margen</th>
                    <th class="px-4 py-3 text-right">%</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($productos as $p)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-mono text-xs text-slate-500">{{ $p->codigo ?: '—' }}</td>
                        <td class="px-4 py-2 font-medium">{{ $p->nombre }}</td>
                        <td class="px-4 py-2 text-slate-500">{{ $p->categoria }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format((float) $p->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-2 text-right text-slate-600">$ {{ number_format((float) $p->costo, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right font-semibold">$ {{ number_format((float) $p->venta, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right {{ $p->margen >= 0 ? 'text-emerald-600' : 'text-red-600' }}">$ {{ number_format((float) $p->margen, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">{{ number_format((float) $p->margen_pct, 1, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-10 text-center text-slate-400">Sin ventas en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        const top = @json($productos->take(10)->map(fn ($p) => [
            'label' => \Illuminate\Support\Str::limit($p->nombre, 28),
            'total' => round((float) $p->venta, 2),
        ])->values());
        if (top.length) {
            new Chart(document.getElementById('chartProd'), {
                type: 'bar',
                data: {
                    labels: top.map(t => t.label),
                    datasets: [{ data: top.map(t => t.total), backgroundColor: '#4f46e5', label: 'Venta $' }],
                },
                options: { indexAxis: 'y', plugins: { legend: { display: false } }, scales: { x: { beginAtZero: true } } },
            });
        }
    </script>
    @endpush
@endsection
