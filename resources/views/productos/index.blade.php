@extends('layouts.app')

@section('titulo', 'Productos')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
            <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre o código"
                   class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todas las categorías</option>
                @foreach ($categorias as $cat)
                    <option value="{{ $cat->id }}" {{ request('categoria') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
                @endforeach
            </select>
            <select name="estado" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Activos</option>
                <option value="inactivos" {{ request('estado') === 'inactivos' ? 'selected' : '' }}>Inactivos</option>
            </select>
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
        </form>

        @can('productos.crear')
            <a href="{{ route('productos.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + Nuevo producto
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Categoría</th>
                    <th class="px-4 py-3 text-right">P. compra</th>
                    <th class="px-4 py-3 text-right">P. venta</th>
                    <th class="px-4 py-3 text-right">IVA %</th>
                    <th class="px-4 py-3 text-right">Stock</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($productos as $p)
                    @php($stockTotal = $p->stockTotal())
                    <tr class="hover:bg-slate-50 {{ ! $p->activo ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $p->codigo }}</td>
                        <td class="px-4 py-3 font-medium">
                            {{ $p->nombre }}
                            @if ($p->pesable)<span class="ml-1 rounded bg-amber-50 px-1.5 py-0.5 text-[10px] font-medium text-amber-700">BALANZA</span>@endif
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->categoria?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-slate-600">$ {{ number_format((float) $p->precio_compra, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $p->precio_venta, 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-slate-600">{{ rtrim(rtrim(number_format((float) $p->alicuota_iva, 2, ',', ''), '0'), ',') }}</td>
                        <td class="px-4 py-3 text-right">
                            <span class="{{ $stockTotal <= (float) $p->stock_minimo ? 'font-semibold text-red-600' : '' }}">
                                {{ rtrim(rtrim(number_format($stockTotal, 3, ',', '.'), '0'), ',') }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('stock.ajustar')
                                    <a href="{{ route('productos.stock', $p) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Stock</a>
                                @endcan
                                @can('productos.editar')
                                    <a href="{{ route('productos.edit', $p) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Editar</a>
                                @endcan
                                @can('productos.eliminar')
                                    <form method="POST" action="{{ route('productos.destroy', $p) }}"
                                          onsubmit="return confirm('¿Eliminar {{ $p->nombre }}?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No se encontraron productos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $productos->links() }}</div>
@endsection
