@extends('layouts.app')

@section('titulo', 'Informe de ventas')

@section('contenido')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Desde</label>
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
    </form>

    <div class="mb-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Total vendido</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($totales['total'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ventas</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($totales['cantidad'], 0, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Ticket promedio</p>
            <p class="mt-1 text-2xl font-bold">$ {{ number_format($totales['promedio'], 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Descuentos</p>
            <p class="mt-1 text-2xl font-bold text-red-600">$ {{ number_format($totales['descuentos'], 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Ventas por día</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($porDia as $dia)
                        <tr>
                            <td class="px-5 py-2">{{ \Carbon\Carbon::parse($dia->dia)->format('d/m/Y') }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ $dia->cantidad }} ventas</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $dia->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Por medio de pago</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @forelse ($porMedio as $medio)
                        <tr>
                            <td class="px-5 py-2">{{ $medio->nombre }}</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $medio->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td class="px-5 py-8 text-center text-slate-400">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>

            <h2 class="border-y border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Por vendedor</h2>
            <table class="w-full text-sm">
                <tbody class="divide-y divide-slate-100">
                    @foreach ($porVendedor as $vendedor)
                        <tr>
                            <td class="px-5 py-2">{{ $vendedor->name }}</td>
                            <td class="px-5 py-2 text-right text-slate-500">{{ $vendedor->cantidad }} ventas</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $vendedor->total, 2, ',', '.') }}</td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white shadow-sm lg:col-span-2">
            <h2 class="border-b border-slate-100 px-5 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Productos más vendidos</h2>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-5 py-2">Producto</th>
                        <th class="px-5 py-2 text-right">Cantidad</th>
                        <th class="px-5 py-2 text-right">Total</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($topProductos as $producto)
                        <tr>
                            <td class="px-5 py-2">{{ $producto->descripcion }}</td>
                            <td class="px-5 py-2 text-right">{{ rtrim(rtrim(number_format((float) $producto->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                            <td class="px-5 py-2 text-right font-semibold">$ {{ number_format((float) $producto->total, 2, ',', '.') }}</td>
                        </tr>
                    @empty
                        <tr><td colspan="3" class="px-5 py-8 text-center text-slate-400">Sin datos en el período.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </div>
@endsection
