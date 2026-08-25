@extends('layouts.app')

@section('titulo', 'Productos')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-sort-hidden :sort="$sort ?? null" :dir="$dir ?? null" />
                <x-filtro-busqueda
                    :url="route('busqueda.productos')"
                    name="buscar"
                    placeholder="Código o nombre…"
                    :value="request('buscar')"
                    :navigate="false"
                    hint="Enter filtra la tabla · ↑↓ Enter o click elige un producto"
                />
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
            <select name="canal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos los canales</option>
                <option value="shopify" {{ request('canal') === 'shopify' ? 'selected' : '' }}>Shopify</option>
                <option value="whatsapp" {{ request('canal') === 'whatsapp' ? 'selected' : '' }}>WhatsApp</option>
                <option value="tiendanube" {{ request('canal') === 'tiendanube' ? 'selected' : '' }}>Tiendanube</option>
            </select>
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
        </form>

        @can('productos.crear')
            <div class="flex gap-2">
                <a href="{{ route('productos.importar') }}"
                   class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
                    Importar CSV
                </a>
                <a href="{{ route('productos.create') }}"
                   class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    + Nuevo producto
                </a>
            </div>
        @endcan
    </div>

    <div class="mb-2 flex flex-wrap items-center justify-between gap-2 text-sm text-slate-600">
        <p>
            <span class="font-semibold text-slate-800">{{ number_format($productos->total(), 0, ',', '.') }}</span>
            producto{{ $productos->total() === 1 ? '' : 's' }}
            @if ($productos->total() > 0)
                <span class="text-slate-400">· mostrando {{ $productos->firstItem() }}–{{ $productos->lastItem() }}</span>
            @endif
        </p>
        @can('productos.editar')
            <div class="flex gap-3">
                <a href="{{ route('productos.canales') }}"
                   class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Publicar en canales →</a>
                <a href="{{ route('productos.precio-masivo') }}"
                   class="text-sm font-medium text-indigo-600 hover:text-indigo-800">Cambio de precio masivo →</a>
            </div>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                        <x-sortable-th column="codigo" label="Código" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="nombre" label="Nombre" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="categoria" label="Categoría" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="precio_compra" label="P. compra" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="desc" align="right" />
                        <x-sortable-th column="precio_venta" label="P. venta" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="desc" align="right" />
                        <x-sortable-th column="iva" label="IVA %" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" align="right" />
                        <x-sortable-th column="stock" label="Stock" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="desc" align="right" />
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
                            @if ($p->es_combo)<span class="ml-1 rounded bg-purple-50 px-1.5 py-0.5 text-[10px] font-medium text-purple-700">COMBO</span>@endif
                            @if ($p->publicar_shopify)<span class="ml-1 rounded bg-lime-50 px-1.5 py-0.5 text-[10px] font-medium text-lime-700">SHOPIFY</span>@endif
                            @if ($p->publicar_whatsapp)<span class="ml-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">WA</span>@endif
                            @if ($p->publicar_tiendanube)<span class="ml-1 rounded bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">TN</span>@endif
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
                                @if ($p->es_combo)
                                    @can('productos.editar')
                                        <a href="{{ route('productos.combo', $p) }}"
                                           class="rounded-lg border border-purple-200 px-2.5 py-1 text-xs text-purple-700 hover:bg-purple-50">Combo</a>
                                    @endcan
                                @else
                                    @can('stock.ajustar')
                                        <a href="{{ route('productos.stock', $p) }}"
                                           class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Stock</a>
                                    @endcan
                                @endif
                                @can('productos.editar')
                                    <a href="{{ route('productos.edit', $p) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Editar</a>
                                    <a href="{{ route('productos.auditoria', $p) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Hist.</a>
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
