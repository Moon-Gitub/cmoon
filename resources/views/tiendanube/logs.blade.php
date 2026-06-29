@extends('layouts.app')

@section('titulo', 'Logs Tiendanube')

@section('contenido')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <div class="flex items-center gap-2 text-sm text-slate-400 mb-1">
                <a href="{{ route('tiendanube.index') }}" class="hover:text-white">Tiendanube</a>
                <span>→</span>
                <span>Logs</span>
            </div>
            <h1 class="text-2xl font-bold">Historial de sincronización</h1>
        </div>
    </div>

    {{-- Filtros --}}
    <div class="bg-slate-800 rounded-xl p-4">
        <form method="GET" class="flex flex-wrap gap-4 items-end">
            <div>
                <label class="block text-sm text-slate-400 mb-1">Tipo</label>
                <select name="tipo" onchange="this.form.submit()"
                        class="bg-slate-700 border border-slate-600 rounded-lg px-3 py-2 text-sm">
                    <option value="">Todos</option>
                    <option value="product_sync" @selected($tipo === 'product_sync')>Productos</option>
                    <option value="stock_sync" @selected($tipo === 'stock_sync')>Stock</option>
                    <option value="order_import" @selected($tipo === 'order_import')>Órdenes</option>
                    <option value="webhook" @selected($tipo === 'webhook')>Webhooks</option>
                    <option value="auth" @selected($tipo === 'auth')>Autenticación</option>
                    <option value="error" @selected($tipo === 'error')>Errores</option>
                </select>
            </div>
            @if($tipo)
                <a href="{{ route('tiendanube.logs') }}" class="px-3 py-2 text-sm text-slate-400 hover:text-white">
                    Limpiar filtro
                </a>
            @endif
        </form>
    </div>

    {{-- Tabla de logs --}}
    <div class="bg-slate-800 rounded-xl overflow-hidden">
        <div class="overflow-x-auto">
            <table class="w-full text-sm">
                <thead>
                    <tr class="text-left text-slate-400 bg-slate-700/50">
                        <th class="px-4 py-3 font-medium">Fecha</th>
                        <th class="px-4 py-3 font-medium">Tipo</th>
                        <th class="px-4 py-3 font-medium">Dirección</th>
                        <th class="px-4 py-3 font-medium">Entidad</th>
                        <th class="px-4 py-3 font-medium">Estado</th>
                        <th class="px-4 py-3 font-medium">Mensaje</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-700/50">
                    @forelse($logs as $log)
                        <tr class="hover:bg-slate-700/30" x-data="{ open: false }">
                            <td class="px-4 py-3 text-slate-400 whitespace-nowrap">
                                {{ $log->created_at->format('d/m/Y H:i:s') }}
                            </td>
                            <td class="px-4 py-3">
                                @php
                                    $tipoColors = [
                                        'product_sync' => 'bg-blue-500/20 text-blue-400',
                                        'stock_sync' => 'bg-green-500/20 text-green-400',
                                        'order_import' => 'bg-purple-500/20 text-purple-400',
                                        'webhook' => 'bg-amber-500/20 text-amber-400',
                                        'auth' => 'bg-cyan-500/20 text-cyan-400',
                                        'error' => 'bg-red-500/20 text-red-400',
                                    ];
                                    $tipoLabels = [
                                        'product_sync' => 'Productos',
                                        'stock_sync' => 'Stock',
                                        'order_import' => 'Órdenes',
                                        'webhook' => 'Webhook',
                                        'auth' => 'Auth',
                                        'error' => 'Error',
                                    ];
                                @endphp
                                <span class="px-2 py-0.5 text-xs rounded {{ $tipoColors[$log->tipo] ?? 'bg-slate-600' }}">
                                    {{ $tipoLabels[$log->tipo] ?? $log->tipo }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                @if($log->direccion === 'push')
                                    <span class="flex items-center gap-1 text-blue-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 8l4 4m0 0l-4 4m4-4H3"/>
                                        </svg>
                                        Push
                                    </span>
                                @elseif($log->direccion === 'pull')
                                    <span class="flex items-center gap-1 text-green-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 16l-4-4m0 0l4-4m-4 4h18"/>
                                        </svg>
                                        Pull
                                    </span>
                                @else
                                    <span class="flex items-center gap-1 text-purple-400">
                                        <svg class="w-4 h-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/>
                                        </svg>
                                        Webhook
                                    </span>
                                @endif
                            </td>
                            <td class="px-4 py-3 text-slate-400">
                                @if($log->entidad_tipo)
                                    {{ ucfirst($log->entidad_tipo) }}
                                    @if($log->entidad_id)
                                        <span class="text-slate-500">#{{ $log->entidad_id }}</span>
                                    @endif
                                @else
                                    -
                                @endif
                            </td>
                            <td class="px-4 py-3">
                                <span class="px-2 py-0.5 text-xs rounded {{ $log->status === 'ok' ? 'bg-green-500/20 text-green-400' : ($log->status === 'pending' ? 'bg-amber-500/20 text-amber-400' : 'bg-red-500/20 text-red-400') }}">
                                    {{ $log->status }}
                                </span>
                            </td>
                            <td class="px-4 py-3">
                                <div class="flex items-center gap-2">
                                    <span class="truncate max-w-xs">{{ $log->mensaje ?? '-' }}</span>
                                    @if($log->request || $log->response)
                                        <button @click="open = !open"
                                                class="p-1 hover:bg-slate-600 rounded transition">
                                            <svg class="w-4 h-4 text-slate-400 transition-transform" :class="{ 'rotate-180': open }" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 9l-7 7-7-7"/>
                                            </svg>
                                        </button>
                                    @endif
                                </div>
                            </td>
                        </tr>
                        @if($log->request || $log->response)
                            <tr x-show="open" x-collapse>
                                <td colspan="6" class="px-4 py-3 bg-slate-900/50">
                                    <div class="grid md:grid-cols-2 gap-4 text-xs font-mono">
                                        @if($log->request)
                                            <div>
                                                <div class="text-slate-400 mb-1">Request:</div>
                                                <pre class="bg-slate-800 p-2 rounded overflow-x-auto max-h-40">{{ json_encode($log->request, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                        @if($log->response)
                                            <div>
                                                <div class="text-slate-400 mb-1">Response:</div>
                                                <pre class="bg-slate-800 p-2 rounded overflow-x-auto max-h-40">{{ json_encode($log->response, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) }}</pre>
                                            </div>
                                        @endif
                                    </div>
                                </td>
                            </tr>
                        @endif
                    @empty
                        <tr>
                            <td colspan="6" class="px-4 py-8 text-center text-slate-400">
                                No hay logs registrados
                            </td>
                        </tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @if($logs->hasPages())
            <div class="px-4 py-3 border-t border-slate-700">
                {{ $logs->links() }}
            </div>
        @endif
    </div>
</div>
@endsection
