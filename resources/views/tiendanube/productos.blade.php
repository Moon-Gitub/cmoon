@extends('layouts.app')

@section('titulo', 'Productos Tiendanube')

@section('contenido')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('tiendanube.index') }}" class="hover:text-white">Tiendanube</a>
                <span>→</span>
                <span>Productos vinculados</span>
            </div>
            <h1 class="text-2xl font-bold">Productos vinculados</h1>
            <p class="text-slate-400 mt-1">{{ $productMaps->total() }} productos sincronizados con Tiendanube</p>
        </div>

        <div class="flex gap-2">
            @if($integracion->auto_create_products)
                <form method="POST" action="{{ route('tiendanube.import.products') }}">
                    @csrf
                    <button type="submit"
                            class="px-4 py-2 bg-green-600 hover:bg-green-500 rounded-lg text-sm font-medium transition">
                        Importar de Tiendanube
                    </button>
                </form>
            @endif
            <form method="POST" action="{{ route('tiendanube.sync.products') }}">
                @csrf
                <button type="submit"
                        class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg text-sm font-medium transition">
                    Sincronizar todos
                </button>
            </form>
        </div>
    </div>

    <div class="bg-slate-800 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 bg-slate-700/50">
                        <th class="px-4 py-3 font-medium">Producto POSMoon</th>
                        <th class="px-4 py-3 font-medium">Código</th>
                        <th class="px-4 py-3 font-medium">ID Tiendanube</th>
                        <th class="px-4 py-3 font-medium">SKU TN</th>
                        <th class="px-4 py-3 font-medium">Última sync</th>
                        <th class="px-4 py-3 font-medium">Acciones</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @forelse($productMaps as $map)
                        <tr class="hover:bg-slate-700/30">
                            <td class="px-4 py-3">
                                @if($map->producto)
                                    <a href="{{ route('productos.edit', $map->producto) }}"
                                       class="text-indigo-400 hover:underline">
                                        {{ $map->producto->nombre }}
                                    </a>
                                @else
                                    <span class="text-slate-500 italic">Producto eliminado</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-400">
                                {{ $map->producto?->codigo ?? '-' }}
                            </td>
                            <td class="px-4 py-3 font-mono">
                                <span class="text-blue-400">{{ $map->tn_product_id }}</span>
                                @if($map->tn_variant_id)
                                    <span class="text-slate-500">/{{ $map->tn_variant_id }}</span>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-slate-400">
                                {{ $map->tn_sku ?? '-' }}
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                {{ $map->last_synced_at?->diffForHumans() ?? 'Nunca' }}
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex gap-2">
                                    @if($integracion->store_url && $map->tn_product_id)
                                        <a href="{{ $integracion->store_url }}/admin/products/{{ $map->tn_product_id }}"
                                           target="_blank"
                                           class="p-1 hover:bg-slate-600 rounded transition"
                                           title="Ver en Tiendanube">
                                            <svg class="w-4 h-4 text-slate-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14"/>
                                            </svg>
                                        </a>
                                    @endif
                                </div>
                            </td>
                        </tr>
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                <div class="flex flex-col items-center gap-2">
                                    <svg class="w-12 h-12 text-slate-600" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                    </svg>
                                    <p>No hay productos vinculados todavía</p>
                                    <p class="text-sm">Sincronizá tu catálogo para comenzar</p>
                                </div>
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($productMaps->hasPages())
            <div class="px-4 py-3 border-t border-slate-700">
                {{ $productMaps->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
