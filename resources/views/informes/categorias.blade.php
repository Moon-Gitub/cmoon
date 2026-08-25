@extends('layouts.app')

@section('titulo', 'Ventas por categoría')

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
        <button class="h-[38px] rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}"
           class="inline-flex h-[38px] items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium hover:bg-slate-50">Exportar CSV</a>
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Total período</p>
            <p class="mt-1 text-3xl font-bold text-indigo-600">$ {{ number_format($total, 2, ',', '.') }}</p>
            <p class="mt-1 text-sm text-slate-500">{{ $filas->count() }} categorías con movimiento</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm lg:col-span-2">
            <h2 class="mb-3 text-sm font-semibold uppercase text-slate-500">Participación</h2>
            <canvas id="chartCat" height="100"></canvas>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3 text-right">Cantidad</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Promedio</th>
                    <th class="px-4 py-3 text-right">% del total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($filas as $f)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2 font-medium">{{ $f->nombre }}</td>
                        <td class="px-4 py-2 text-right">{{ rtrim(rtrim(number_format((float) $f->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-2 text-right font-semibold">$ {{ number_format((float) $f->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right text-slate-600">$ {{ number_format((float) $f->promedio, 2, ',', '.') }}</td>
                        <td class="px-4 py-2 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <div class="h-1.5 w-20 overflow-hidden rounded bg-slate-100">
                                    <div class="h-full bg-indigo-500" style="width: {{ min(100, (float) $f->porcentaje) }}%"></div>
                                </div>
                                <span>{{ number_format((float) $f->porcentaje, 1, ',', '.') }}%</span>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Sin datos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    @push('scripts')
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <script>
        @php
            $chartCat = $filas->map(function ($f) {
                return [
                    'label' => $f->nombre,
                    'total' => round((float) $f->total, 2),
                ];
            })->values();
        @endphp
        const rows = @json($chartCat);
        if (rows.length) {
            new Chart(document.getElementById('chartCat'), {
                type: 'pie',
                data: {
                    labels: rows.map(r => r.label),
                    datasets: [{
                        data: rows.map(r => r.total),
                        backgroundColor: ['#4f46e5','#06b6d4','#10b981','#f59e0b','#ef4444','#8b5cf6','#64748b','#ec4899','#14b8a6','#a3e635'],
                    }],
                },
                options: { plugins: { legend: { position: 'right' } } },
            });
        }
    </script>
    @endpush
@endsection
