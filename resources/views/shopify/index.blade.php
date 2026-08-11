@extends('layouts.app')

@section('titulo', 'Integración Shopify')

@section('contenido')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">Integración Shopify</h1>
            <p class="text-slate-500 mt-1">Sincronizá productos y recibí órdenes online en POSMoon</p>
        </div>
        @if($integracion)
            <a href="{{ route('shopify.logs') }}" class="text-sm text-indigo-600 hover:underline">Ver logs →</a>
        @endif
    </div>

    @if(session('ok'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('ok') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">{{ session('error') }}</div>
    @endif

    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
        Documentación: <code class="rounded bg-slate-100 px-1">docs/18-integracion-shopify.md</code>
        · API REST: <a class="text-indigo-600 hover:underline" href="/docs/api" target="_blank">/docs/api</a>
        · Webhook: <code class="rounded bg-slate-100 px-1">{{ url('/webhooks/shopify') }}</code>
    </div>

    @if(!$integracion)
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="text-lg font-semibold mb-4">Conectar tienda (Custom App / Admin API token)</h2>
            <form method="POST" action="{{ route('shopify.store') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Store domain *</label>
                    <input name="store_domain" value="{{ old('store_domain', config('shopify.store_domain')) }}" required
                           placeholder="mi-tienda.myshopify.com"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Admin API access token *</label>
                    <input name="access_token" type="password" value="{{ old('access_token') }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2"
                           autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">API key (opcional)</label>
                    <input name="api_key" value="{{ old('api_key', config('shopify.api_key')) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">API secret (HMAC webhooks)</label>
                    <input name="api_secret" type="password" value="{{ old('api_secret') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2" autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">Webhook secret (si difiere)</label>
                    <input name="webhook_secret" type="password" value="{{ old('webhook_secret') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2" autocomplete="off">
                </div>
                <div>
                    <label class="block text-sm font-medium mb-1">API version</label>
                    <input name="api_version" value="{{ old('api_version', config('shopify.api_version')) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div class="md:col-span-2">
                    <label class="block text-sm font-medium mb-1">Sucursal default (órdenes)</label>
                    <select name="default_sucursal_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                        <option value="">—</option>
                        @foreach($sucursales as $s)
                            <option value="{{ $s->id }}">{{ $s->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <div class="md:col-span-2">
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 font-medium text-white hover:bg-indigo-500">
                        Guardar y conectar
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-3">
            <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-1">
                <div class="flex items-center gap-2 mb-3">
                    <span class="h-2.5 w-2.5 rounded-full {{ $integracion->activo ? 'bg-emerald-500' : 'bg-slate-400' }}"></span>
                    <h2 class="font-semibold">{{ $integracion->store_name ?: $integracion->store_domain }}</h2>
                </div>
                <p class="text-sm text-slate-500 break-all">{{ $integracion->store_domain }}</p>
                <p class="text-xs text-slate-400 mt-1">API {{ $integracion->api_version ?: config('shopify.api_version') }}</p>

                @if($stats)
                    <dl class="mt-4 space-y-2 text-sm">
                        <div class="flex justify-between"><dt>Productos mapeados</dt><dd class="font-medium">{{ $stats['productos_vinculados'] }}</dd></div>
                        <div class="flex justify-between"><dt>Ventas Shopify</dt><dd class="font-medium">{{ $stats['ventas_shopify'] }}</dd></div>
                        <div class="flex justify-between"><dt>Errores 24h</dt><dd class="font-medium">{{ $stats['errores_24h'] }}</dd></div>
                        <div class="flex justify-between"><dt>Último sync productos</dt><dd class="text-xs">{{ $stats['last_product_sync']?->diffForHumans() ?? '—' }}</dd></div>
                    </dl>
                @endif

                <div class="mt-4 flex flex-wrap gap-2">
                    <form method="POST" action="{{ route('shopify.test') }}">@csrf
                        <button class="rounded-lg border px-3 py-1.5 text-sm hover:bg-slate-50">Probar conexión</button>
                    </form>
                    <form method="POST" action="{{ route('shopify.disconnect') }}" onsubmit="return confirm('¿Desconectar Shopify?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600 hover:bg-red-50">Desconectar</button>
                    </form>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6 lg:col-span-2 space-y-6">
                <form method="POST" action="{{ route('shopify.config') }}" class="space-y-4">
                    @csrf @method('PATCH')
                    <h3 class="font-semibold">Configuración</h3>
                    <div class="grid gap-3 sm:grid-cols-2">
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sync_products" value="1" @checked($integracion->sync_products)> Sync productos</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="sync_orders" value="1" @checked($integracion->sync_orders)> Importar órdenes</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="auto_create_products" value="1" @checked($integracion->auto_create_products)> Pull: crear productos locales</label>
                        <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="push_products" value="1" @checked($integracion->push_products)> Push: enviar productos a Shopify</label>
                    </div>
                    <div>
                        <label class="block text-sm font-medium mb-1">Sucursal default</label>
                        <select name="default_sucursal_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                            <option value="">—</option>
                            @foreach($sucursales as $s)
                                <option value="{{ $s->id }}" @selected($integracion->default_sucursal_id == $s->id)>{{ $s->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm text-white hover:bg-slate-700">Guardar config</button>
                </form>

                <div class="flex flex-wrap gap-2 border-t pt-4">
                    <form method="POST" action="{{ route('shopify.import.products') }}">@csrf
                        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm text-white hover:bg-indigo-500">Importar productos (pull)</button>
                    </form>
                    <form method="POST" action="{{ route('shopify.sync.products') }}">@csrf
                        <button class="rounded-lg border px-4 py-2 text-sm hover:bg-slate-50">Push productos</button>
                    </form>
                </div>
            </div>
        </div>

        @if($logs->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
                <div class="border-b px-4 py-3 font-medium">Últimos logs</div>
                <table class="min-w-full text-sm">
                    <thead class="bg-slate-50 text-left text-slate-500">
                        <tr>
                            <th class="px-4 py-2">Fecha</th>
                            <th class="px-4 py-2">Tipo</th>
                            <th class="px-4 py-2">Status</th>
                            <th class="px-4 py-2">Mensaje</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($logs as $log)
                            <tr class="border-t">
                                <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at }}</td>
                                <td class="px-4 py-2">{{ $log->tipo }}/{{ $log->direccion }}</td>
                                <td class="px-4 py-2">
                                    <span class="{{ $log->status === 'ok' ? 'text-emerald-600' : 'text-red-600' }}">{{ $log->status }}</span>
                                </td>
                                <td class="px-4 py-2">{{ $log->mensaje }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection
