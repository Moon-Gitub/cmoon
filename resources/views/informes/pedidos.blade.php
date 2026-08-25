@extends('layouts.app')

@section('titulo', 'Gestión de pedidos')

@section('contenido')
    @include('informes._nav')

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Días de análisis</label>
            <input type="number" name="dias_analisis" min="7" max="90" value="{{ $diasAnalisis }}"
                   class="h-[38px] w-28 rounded-lg border border-slate-300 px-3 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Días de cobertura deseados</label>
            <input type="number" name="dias_cobertura" min="7" max="90" value="{{ $diasCobertura }}"
                   class="h-[38px] w-28 rounded-lg border border-slate-300 px-3 text-sm">
        </div>
        <label class="flex h-[38px] items-center gap-2 text-sm text-slate-600">
            <input type="checkbox" name="solo_pedir" value="1" @checked($soloCriticos)>
            Solo con pedido sugerido
        </label>
        <button class="h-[38px] rounded-lg bg-indigo-600 px-4 text-sm font-semibold text-white hover:bg-indigo-700">Calcular</button>
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}"
           class="inline-flex h-[38px] items-center rounded-lg border border-slate-300 bg-white px-4 text-sm font-medium hover:bg-slate-50">Exportar CSV</a>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-3 lg:grid-cols-5">
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Inversión sugerida</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($resumen['inversion_total'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Críticos (≤3 días)</p>
            <p class="mt-1 text-2xl font-bold text-red-600">{{ $resumen['criticos'] }}</p>
            <p class="text-xs text-slate-500">$ {{ number_format($resumen['inversion_criticos'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Urgentes (≤7 días)</p>
            <p class="mt-1 text-2xl font-bold text-amber-600">{{ $resumen['urgentes'] }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Ganancia esperada</p>
            <p class="mt-1 text-2xl font-bold text-emerald-600">$ {{ number_format($resumen['ganancia_esperada'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <p class="text-xs font-semibold uppercase text-slate-500">Productos analizados</p>
            <p class="mt-1 text-2xl font-bold">{{ $resumen['productos'] }}</p>
        </div>
    </div>

    <p class="mb-3 text-sm text-slate-500">
        Calcula velocidad de venta de los últimos {{ $diasAnalisis }} días y sugiere reponer hasta {{ $diasCobertura }} días de cobertura.
        Estado: <span class="font-semibold text-red-600">crítico</span> ≤3 días ·
        <span class="font-semibold text-amber-600">urgente</span> ≤7 ·
        <span class="font-semibold text-slate-600">normal</span> el resto.
    </p>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-3">Estado</th>
                    <th class="px-3 py-3">Código</th>
                    <th class="px-3 py-3">Producto</th>
                    <th class="px-3 py-3 text-right">Stock</th>
                    <th class="px-3 py-3 text-right">Venta/día</th>
                    <th class="px-3 py-3 text-right">Cobertura</th>
                    <th class="px-3 py-3 text-right">Pedir</th>
                    <th class="px-3 py-3 text-right">Inversión</th>
                    <th class="px-3 py-3 text-right">Ganancia</th>
                    <th class="px-3 py-3 text-right">ROI</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($items as $i)
                    <tr class="hover:bg-slate-50 {{ $i->estado === 'critico' ? 'bg-red-50/40' : ($i->estado === 'urgente' ? 'bg-amber-50/40' : '') }}">
                        <td class="px-3 py-2">
                            <span class="rounded px-1.5 py-0.5 text-[10px] font-bold uppercase
                                {{ $i->estado === 'critico' ? 'bg-red-100 text-red-700' : ($i->estado === 'urgente' ? 'bg-amber-100 text-amber-800' : 'bg-slate-100 text-slate-600') }}">
                                {{ $i->estado }}
                            </span>
                        </td>
                        <td class="px-3 py-2 font-mono text-xs text-slate-500">{{ $i->codigo }}</td>
                        <td class="px-3 py-2 font-medium">{{ $i->nombre }}</td>
                        <td class="px-3 py-2 text-right">{{ rtrim(rtrim(number_format((float) $i->stock, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="px-3 py-2 text-right text-slate-600">{{ number_format((float) $i->promedio_diario, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold">{{ $i->dias_cobertura >= 999 ? '∞' : number_format((float) $i->dias_cobertura, 1, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right font-semibold text-indigo-700">{{ rtrim(rtrim(number_format((float) $i->cantidad_sugerida, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="px-3 py-2 text-right">$ {{ number_format((float) $i->inversion, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right text-emerald-600">$ {{ number_format((float) $i->ganancia, 2, ',', '.') }}</td>
                        <td class="px-3 py-2 text-right">{{ number_format((float) $i->roi, 1, ',', '.') }}%</td>
                    </tr>
                @empty
                    <tr><td colspan="10" class="px-4 py-10 text-center text-slate-400">Sin productos con ventas en el período de análisis.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
