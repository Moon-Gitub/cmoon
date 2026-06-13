@extends('layouts.app')

@section('titulo', 'Informe de stock')

@section('contenido')
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Stock valorizado a costo</p>
            <p class="mt-1 text-2xl font-bold">$ {{ number_format($valorCosto, 2, ',', '.') }}</p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Stock valorizado a precio de venta</p>
            <p class="mt-1 text-2xl font-bold text-indigo-600">$ {{ number_format($valorVenta, 2, ',', '.') }}</p>
        </div>
    </div>

    <div class="mb-4 flex gap-2">
        <a href="{{ route('informes.stock') }}"
           class="rounded-lg px-4 py-2 text-sm font-medium {{ request('filtro') !== 'bajo' ? 'bg-indigo-600 text-white' : 'border border-slate-300 bg-white hover:bg-slate-50' }}">
            Todos
        </a>
        <a href="{{ route('informes.stock', ['filtro' => 'bajo']) }}"
           class="rounded-lg px-4 py-2 text-sm font-medium {{ request('filtro') === 'bajo' ? 'bg-red-600 text-white' : 'border border-slate-300 bg-white hover:bg-slate-50' }}">
            Stock bajo
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Producto</th>
                    <th class="px-4 py-3">Categoría</th>
                    @foreach ($sucursales as $sucursal)
                        <th class="px-4 py-3 text-right">{{ $sucursal->nombre }}</th>
                    @endforeach
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3 text-right">Mínimo</th>
                    <th class="px-4 py-3 text-right">Valor costo</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($productos as $producto)
                    @php($total = $producto->stockTotal())
                    <tr class="hover:bg-slate-50 {{ $total <= (float) $producto->stock_minimo ? 'bg-red-50/50' : '' }}">
                        <td class="px-4 py-2.5">
                            <span class="font-medium">{{ $producto->nombre }}</span>
                            <span class="font-mono text-xs text-slate-400">{{ $producto->codigo }}</span>
                        </td>
                        <td class="px-4 py-2.5 text-slate-500">{{ $producto->categoria?->nombre ?? '—' }}</td>
                        @foreach ($sucursales as $sucursal)
                            <td class="px-4 py-2.5 text-right">{{ rtrim(rtrim(number_format($producto->stockEn($sucursal->id), 3, ',', '.'), '0'), ',') }}</td>
                        @endforeach
                        <td class="px-4 py-2.5 text-right font-semibold {{ $total <= (float) $producto->stock_minimo ? 'text-red-600' : '' }}">
                            {{ rtrim(rtrim(number_format($total, 3, ',', '.'), '0'), ',') }}
                        </td>
                        <td class="px-4 py-2.5 text-right text-slate-400">{{ rtrim(rtrim(number_format((float) $producto->stock_minimo, 3, ',', '.'), '0'), ',') }}</td>
                        <td class="px-4 py-2.5 text-right">$ {{ number_format($total * (float) $producto->precio_compra, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="{{ 5 + $sucursales->count() }}" class="px-4 py-10 text-center text-slate-400">Sin productos para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $productos->links() }}</div>
@endsection
