@extends('layouts.app')

@section('titulo', 'Integración Tiendanube')

@section('contenido')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Integración Tiendanube</h1>
            <p class="text-slate-400 mt-1">Sincronizá productos, stock y órdenes con tu tienda online</p>
        </div>
        @if($integracion)
            <a href="{{ route('tiendanube.logs') }}"
               class="text-sm text-indigo-400 hover:text-indigo-300">
                Ver historial de sincronización →
            </a>
        @endif
    </div>

    @if(!$configured)
        <div class="bg-amber-500/10 border border-amber-500/30 rounded-lg p-4">
            <div class="flex gap-3">
                <svg class="w-5 h-5 text-amber-400 shrink-0 mt-0.5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z"/>
                </svg>
                <div>
                    <h3 class="font-medium text-amber-400">Configuración requerida</h3>
                    <p class="text-sm text-slate-300 mt-1">
                        Agregá las variables <code class="bg-slate-700 px-1 rounded">TIENDANUBE_CLIENT_ID</code> y
                        <code class="bg-slate-700 px-1 rounded">TIENDANUBE_CLIENT_SECRET</code> en tu archivo .env.
                        Podés obtenerlas en <a href="https://partners.tiendanube.com/" target="_blank" class="text-indigo-400 hover:underline">partners.tiendanube.com</a>
                    </p>
                </div>
            </div>
        </div>
    @endif

    @if(!$integracion)
        {{-- Estado: No conectado --}}
        <div class="bg-slate-800 rounded-xl p-8 text-center">
            <div class="w-16 h-16 bg-blue-500/20 rounded-full flex items-center justify-center mx-auto mb-4">
                <svg class="w-8 h-8 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                </svg>
            </div>
            <h2 class="text-xl font-semibold mb-2">Conectá tu tienda Tiendanube</h2>
            <p class="text-slate-400 mb-6 max-w-md mx-auto">
                Sincronizá automáticamente productos, stock y recibí las órdenes de tu tienda online directamente en POSMoon.
            </p>

            <div class="grid md:grid-cols-3 gap-4 mb-8 max-w-2xl mx-auto text-left">
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <div class="w-8 h-8 bg-green-500/20 rounded-lg flex items-center justify-center mb-2">
                        <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                        </svg>
                    </div>
                    <h3 class="font-medium text-sm">Productos</h3>
                    <p class="text-xs text-slate-400">Exportá tu catálogo a Tiendanube</p>
                </div>
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <div class="w-8 h-8 bg-blue-500/20 rounded-lg flex items-center justify-center mb-2">
                        <svg class="w-4 h-4 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                        </svg>
                    </div>
                    <h3 class="font-medium text-sm">Stock</h3>
                    <p class="text-xs text-slate-400">Stock sincronizado en tiempo real</p>
                </div>
                <div class="bg-slate-700/50 rounded-lg p-4">
                    <div class="w-8 h-8 bg-purple-500/20 rounded-lg flex items-center justify-center mb-2">
                        <svg class="w-4 h-4 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                        </svg>
                    </div>
                    <h3 class="font-medium text-sm">Órdenes</h3>
                    <p class="text-xs text-slate-400">Recibí ventas online automáticamente</p>
                </div>
            </div>

            <form method="POST" action="{{ route('tiendanube.connect') }}">
                @csrf
                <button type="submit"
                        @disabled(!$configured)
                        class="inline-flex items-center gap-2 px-6 py-3 bg-blue-600 hover:bg-blue-500 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg font-medium transition">
                    <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/>
                    </svg>
                    Conectar con Tiendanube
                </button>
            </form>
        </div>
    @else
        {{-- Estado: Conectado --}}
        <div class="grid lg:grid-cols-3 gap-6">
            {{-- Info de la tienda --}}
            <div class="bg-slate-800 rounded-xl p-6">
                <div class="flex items-start justify-between mb-4">
                    <div class="flex items-center gap-3">
                        <div class="w-10 h-10 bg-green-500/20 rounded-lg flex items-center justify-center">
                            <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                            </svg>
                        </div>
                        <div>
                            <h3 class="font-semibold">Tienda conectada</h3>
                            <p class="text-sm text-slate-400">ID: {{ $integracion->store_id }}</p>
                        </div>
                    </div>
                    <span class="px-2 py-1 text-xs rounded-full {{ $integracion->activo ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}">
                        {{ $integracion->activo ? 'Activa' : 'Inactiva' }}
                    </span>
                </div>

                <div class="space-y-3 text-sm">
                    @if($integracion->store_name)
                        <div class="flex justify-between">
                            <span class="text-slate-400">Tienda</span>
                            <span class="font-medium">{{ $integracion->store_name }}</span>
                        </div>
                    @endif
                    @if($integracion->store_url)
                        <div class="flex justify-between">
                            <span class="text-slate-400">URL</span>
                            <a href="{{ $integracion->store_url }}" target="_blank" class="text-indigo-400 hover:underline truncate ml-2">
                                {{ Str::limit($integracion->store_url, 25) }}
                            </a>
                        </div>
                    @endif
                    <div class="flex justify-between">
                        <span class="text-slate-400">Conectada</span>
                        <span>{{ $integracion->created_at->diffForHumans() }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700 flex gap-2">
                    <form method="POST" action="{{ route('tiendanube.test') }}" class="flex-1">
                        @csrf
                        <button type="submit" class="w-full px-3 py-2 text-sm bg-slate-700 hover:bg-slate-600 rounded-lg transition">
                            Probar conexión
                        </button>
                    </form>
                    <form method="POST" action="{{ route('tiendanube.disconnect') }}"
                          onsubmit="return confirm('¿Desconectar la tienda? Se perderá la configuración.')"
                          class="flex-1">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="w-full px-3 py-2 text-sm bg-red-600/20 hover:bg-red-600/30 text-red-400 rounded-lg transition">
                            Desconectar
                        </button>
                    </form>
                </div>
            </div>

            {{-- Estadísticas --}}
            <div class="bg-slate-800 rounded-xl p-6">
                <h3 class="font-semibold mb-4">Estadísticas</h3>
                <div class="space-y-4">
                    <div class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-indigo-500/20 rounded flex items-center justify-center">
                                <svg class="w-4 h-4 text-indigo-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                            </div>
                            <span class="text-sm">Productos vinculados</span>
                        </div>
                        <span class="font-bold text-lg">{{ number_format($stats['productos_vinculados']) }}</span>
                    </div>
                    <div class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg">
                        <div class="flex items-center gap-3">
                            <div class="w-8 h-8 bg-green-500/20 rounded flex items-center justify-center">
                                <svg class="w-4 h-4 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z"/>
                                </svg>
                            </div>
                            <span class="text-sm">Categorías vinculadas</span>
                        </div>
                        <span class="font-bold text-lg">{{ number_format($stats['categorias_vinculadas']) }}</span>
                    </div>
                </div>

                <div class="mt-4 pt-4 border-t border-slate-700 space-y-2 text-sm">
                    <div class="flex justify-between text-slate-400">
                        <span>Última sync productos</span>
                        <span>{{ $stats['last_product_sync']?->diffForHumans() ?? 'Nunca' }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Última sync stock</span>
                        <span>{{ $stats['last_stock_sync']?->diffForHumans() ?? 'Nunca' }}</span>
                    </div>
                    <div class="flex justify-between text-slate-400">
                        <span>Última sync órdenes</span>
                        <span>{{ $stats['last_order_sync']?->diffForHumans() ?? 'Nunca' }}</span>
                    </div>
                </div>
            </div>

            {{-- Acciones rápidas --}}
            <div class="bg-slate-800 rounded-xl p-6">
                <h3 class="font-semibold mb-4">Acciones</h3>
                <div class="space-y-3">
                    <form method="POST" action="{{ route('tiendanube.sync.products') }}">
                        @csrf
                        <button type="submit"
                                @disabled(!$integracion->sync_products)
                                class="w-full flex items-center justify-between p-3 bg-slate-700/50 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/>
                                </svg>
                                <span class="text-sm">Sincronizar productos</span>
                            </div>
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('tiendanube.sync.stock') }}">
                        @csrf
                        <button type="submit"
                                @disabled(!$integracion->sync_stock || !$integracion->default_sucursal_id)
                                class="w-full flex items-center justify-between p-3 bg-slate-700/50 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                                </svg>
                                <span class="text-sm">Sincronizar stock</span>
                            </div>
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>

                    <form method="POST" action="{{ route('tiendanube.import.orders') }}">
                        @csrf
                        <button type="submit"
                                @disabled(!$integracion->sync_orders)
                                class="w-full flex items-center justify-between p-3 bg-slate-700/50 hover:bg-slate-700 disabled:opacity-50 disabled:cursor-not-allowed rounded-lg transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/>
                                </svg>
                                <span class="text-sm">Importar órdenes recientes</span>
                            </div>
                            <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                            </svg>
                        </button>
                    </form>

                    <a href="{{ route('tiendanube.productos') }}"
                       class="w-full flex items-center justify-between p-3 bg-slate-700/50 hover:bg-slate-700 rounded-lg transition">
                        <div class="flex items-center gap-3">
                            <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/>
                            </svg>
                            <span class="text-sm">Ver productos vinculados</span>
                        </div>
                        <svg class="w-4 h-4 text-slate-500" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/>
                        </svg>
                    </a>
                </div>
            </div>
        </div>

        {{-- Configuración de sincronización --}}
        <div class="bg-slate-800 rounded-xl p-6">
            <h3 class="font-semibold mb-4">Configuración de sincronización</h3>

            <form method="POST" action="{{ route('tiendanube.config') }}" class="space-y-6">
                @csrf
                @method('PATCH')

                <div class="grid md:grid-cols-2 gap-6">
                    <div class="space-y-4">
                        <h4 class="text-sm font-medium text-slate-400 uppercase tracking-wider">Qué sincronizar</h4>

                        <label class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 7l-8 4-8-4m16 0l-8 4m8-4v10l-8 4m0-10L4 7m8 4v10M4 7v10l8 4"/>
                                </svg>
                                <div>
                                    <span class="font-medium">Productos</span>
                                    <p class="text-xs text-slate-400">Exportar catálogo a Tiendanube</p>
                                </div>
                            </div>
                            <input type="checkbox" name="sync_products" value="1"
                                   @checked($integracion->sync_products)
                                   class="w-5 h-5 rounded bg-slate-600 border-slate-500 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-800">
                        </label>

                        <label class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 12l3-3 3 3 4-4M8 21l4-4 4 4M3 4h18M4 4h16v12a1 1 0 01-1 1H5a1 1 0 01-1-1V4z"/>
                                </svg>
                                <div>
                                    <span class="font-medium">Stock</span>
                                    <p class="text-xs text-slate-400">Actualizar stock automáticamente</p>
                                </div>
                            </div>
                            <input type="checkbox" name="sync_stock" value="1"
                                   @checked($integracion->sync_stock)
                                   class="w-5 h-5 rounded bg-slate-600 border-slate-500 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-800">
                        </label>

                        <label class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/>
                                </svg>
                                <div>
                                    <span class="font-medium">Órdenes</span>
                                    <p class="text-xs text-slate-400">Importar ventas de Tiendanube</p>
                                </div>
                            </div>
                            <input type="checkbox" name="sync_orders" value="1"
                                   @checked($integracion->sync_orders)
                                   class="w-5 h-5 rounded bg-slate-600 border-slate-500 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-800">
                        </label>

                        <label class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-amber-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 20h5v-2a3 3 0 00-5.356-1.857M17 20H7m10 0v-2c0-.656-.126-1.283-.356-1.857M7 20H2v-2a3 3 0 015.356-1.857M7 20v-2c0-.656.126-1.283.356-1.857m0 0a5.002 5.002 0 019.288 0M15 7a3 3 0 11-6 0 3 3 0 016 0z"/>
                                </svg>
                                <div>
                                    <span class="font-medium">Clientes</span>
                                    <p class="text-xs text-slate-400">Sincronizar clientes</p>
                                </div>
                            </div>
                            <input type="checkbox" name="sync_customers" value="1"
                                   @checked($integracion->sync_customers)
                                   class="w-5 h-5 rounded bg-slate-600 border-slate-500 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-800">
                        </label>
                    </div>

                    <div class="space-y-4">
                        <h4 class="text-sm font-medium text-slate-400 uppercase tracking-wider">Opciones</h4>

                        <label class="flex items-center justify-between p-3 bg-slate-700/50 rounded-lg cursor-pointer hover:bg-slate-700 transition">
                            <div class="flex items-center gap-3">
                                <svg class="w-5 h-5 text-cyan-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6"/>
                                </svg>
                                <div>
                                    <span class="font-medium">Crear productos automáticamente</span>
                                    <p class="text-xs text-slate-400">Al importar de Tiendanube</p>
                                </div>
                            </div>
                            <input type="checkbox" name="auto_create_products" value="1"
                                   @checked($integracion->auto_create_products)
                                   class="w-5 h-5 rounded bg-slate-600 border-slate-500 text-indigo-500 focus:ring-indigo-500 focus:ring-offset-slate-800">
                        </label>

                        <div class="p-3 bg-slate-700/50 rounded-lg">
                            <label class="block text-sm font-medium mb-2">Sucursal para stock</label>
                            <select name="default_sucursal_id"
                                    class="w-full bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm focus:ring-2 focus:ring-indigo-500 focus:border-transparent">
                                <option value="">Seleccionar sucursal...</option>
                                @foreach($sucursales as $sucursal)
                                    <option value="{{ $sucursal->id }}"
                                            @selected($integracion->default_sucursal_id == $sucursal->id)>
                                        {{ $sucursal->nombre }}
                                    </option>
                                @endforeach
                            </select>
                            <p class="text-xs text-slate-400 mt-1">Stock de esta sucursal se sincroniza con Tiendanube</p>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end pt-4 border-t border-slate-700">
                    <button type="submit"
                            class="px-4 py-2 bg-indigo-600 hover:bg-indigo-500 rounded-lg font-medium transition">
                        Guardar configuración
                    </button>
                </div>
            </form>
        </div>

        {{-- Logs recientes --}}
        @if($logs->isNotEmpty())
            <div class="bg-slate-800 rounded-xl p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="font-semibold">Actividad reciente</h3>
                    <a href="{{ route('tiendanube.logs') }}" class="text-sm text-indigo-400 hover:underline">Ver todo</a>
                </div>

                <div class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="text-left text-slate-400 border-b border-slate-700">
                                <th class="pb-2 font-medium">Fecha</th>
                                <th class="pb-2 font-medium">Tipo</th>
                                <th class="pb-2 font-medium">Dirección</th>
                                <th class="pb-2 font-medium">Estado</th>
                                <th class="pb-2 font-medium">Mensaje</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-700/50">
                            @foreach($logs as $log)
                                <tr class="hover:bg-slate-700/30">
                                    <td class="py-2 text-slate-400">{{ $log->created_at->format('d/m H:i') }}</td>
                                    <td class="py-2">
                                        <span class="px-2 py-0.5 text-xs rounded bg-slate-700">{{ $log->tipo }}</span>
                                    </td>
                                    <td class="py-2">
                                        @if($log->direccion === 'push')
                                            <span class="text-blue-400">→ TN</span>
                                        @elseif($log->direccion === 'pull')
                                            <span class="text-green-400">← TN</span>
                                        @else
                                            <span class="text-purple-400">webhook</span>
                                        @endif
                                    </td>
                                    <td class="py-2">
                                        <span class="px-2 py-0.5 text-xs rounded {{ $log->status === 'ok' ? 'bg-green-500/20 text-green-400' : 'bg-red-500/20 text-red-400' }}">
                                            {{ $log->status }}
                                        </span>
                                    </td>
                                    <td class="py-2 text-slate-300 truncate max-w-xs">{{ $log->mensaje ?? '-' }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>
                </div>
            </div>
        @endif
    @endif
</div>
@endsection
