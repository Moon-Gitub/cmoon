@extends('layouts.app')

@section('titulo', 'WhatsApp (YCloud)')

@section('contenido')
<div class="space-y-6">
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-2xl font-bold">WhatsApp + catálogo</h1>
            <p class="mt-1 text-slate-500">Publicá productos elegidos y respondé consultas con IA (YCloud).</p>
        </div>
        @if($integracion)
            <a href="{{ route('ycloud.mensajes') }}" class="text-sm text-indigo-600 hover:underline">Ver chats →</a>
        @endif
    </div>

    @if(session('ok'))
        <div class="rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('ok') }}</div>
    @endif
    @if(session('error'))
        <div class="rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-red-800">{{ session('error') }}</div>
    @endif

    <div class="grid gap-3 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">En WhatsApp</div>
            <div class="text-2xl font-semibold">{{ $stats['whatsapp'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">En Shopify</div>
            <div class="text-2xl font-semibold">{{ $stats['shopify'] }}</div>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-4">
            <div class="text-xs uppercase text-slate-500">En Tiendanube</div>
            <div class="text-2xl font-semibold">{{ $stats['tiendanube'] }}</div>
        </div>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
        Webhook inbound: <code class="rounded bg-slate-100 px-1">{{ url('/webhooks/ycloud') }}</code>
        · Elegí productos en <a class="text-indigo-600 hover:underline" href="{{ route('productos.canales') }}">Publicar en canales</a>
        · Docs: <code class="rounded bg-slate-100 px-1">docs/20-whatsapp-ycloud.md</code>
    </div>

    @if(!$integracion)
        <div class="rounded-xl border border-slate-200 bg-white p-6">
            <h2 class="mb-4 text-lg font-semibold">Conectar YCloud</h2>
            <form method="POST" action="{{ route('ycloud.store') }}" class="grid gap-4 md:grid-cols-2">
                @csrf
                <div class="md:col-span-2">
                    <label class="mb-1 block text-sm font-medium">API key *</label>
                    <input name="api_key" type="password" required autocomplete="off"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Número WhatsApp Business (E.164) *</label>
                    <input name="phone_from" required placeholder="+54911..."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">WABA ID (opcional)</label>
                    <input name="waba_id" class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Webhook secret (opcional)</label>
                    <input name="webhook_secret" type="password" autocomplete="off"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Plantilla de catálogo Meta</label>
                    <input name="catalog_template" placeholder="intro_catalog_offer"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2">
                </div>
                <div class="md:col-span-2">
                    <button class="rounded-lg bg-emerald-600 px-4 py-2 font-medium text-white hover:bg-emerald-500">
                        Guardar y conectar
                    </button>
                </div>
            </form>
        </div>
    @else
        <div class="grid gap-6 lg:grid-cols-2">
            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="mb-3 text-lg font-semibold">Bot</h2>
                <p class="mb-4 text-sm text-slate-500">Número: {{ $integracion->phone_from }} ·
                    {{ $integracion->activo && $integracion->bot_activo ? 'activo' : 'pausado' }}</p>
                <form method="POST" action="{{ route('ycloud.config') }}" class="space-y-3">
                    @csrf
                    @method('PATCH')
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="bot_activo" value="1" {{ $integracion->bot_activo ? 'checked' : '' }}>
                        Bot activo
                    </label>
                    <label class="flex items-center gap-2 text-sm">
                        <input type="checkbox" name="auto_reply" value="1" {{ $integracion->auto_reply ? 'checked' : '' }}>
                        Responder consultas automáticamente
                    </label>
                    <div>
                        <label class="mb-1 block text-sm">Plantilla catálogo</label>
                        <input name="catalog_template" value="{{ $integracion->catalog_template }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-medium text-white">Guardar</button>
                </form>
                <div class="mt-4 flex gap-2">
                    <form method="POST" action="{{ route('ycloud.test') }}">@csrf
                        <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-sm">Probar conexión</button>
                    </form>
                    <form method="POST" action="{{ route('ycloud.disconnect') }}" onsubmit="return confirm('¿Pausar WhatsApp?')">
                        @csrf @method('DELETE')
                        <button class="rounded-lg border border-red-200 px-3 py-1.5 text-sm text-red-600">Desconectar</button>
                    </form>
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-6">
                <h2 class="mb-3 text-lg font-semibold">Probar consulta (sin enviar WhatsApp)</h2>
                <form method="POST" action="{{ route('ycloud.probar') }}" class="space-y-3">
                    @csrf
                    <textarea name="consulta" rows="3" required placeholder="¿Tenés calza negra?"
                              class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('consulta') }}</textarea>
                    <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-medium text-white">Simular respuesta</button>
                </form>
                @if(session('prueba'))
                    @php($p = session('prueba'))
                    <pre class="mt-4 whitespace-pre-wrap rounded-lg bg-slate-50 p-3 text-sm">{{ $p['texto'] }}</pre>
                    <p class="mt-1 text-xs text-slate-500">
                        Productos usados: {{ count($p['producto_ids'] ?? []) }}
                        @if(!empty($p['handoff'])) · handoff a humano @endif
                    </p>
                @endif
            </div>
        </div>

        @if($mensajes->isNotEmpty())
            <div class="rounded-xl border border-slate-200 bg-white p-4">
                <h2 class="mb-3 font-semibold">Últimos mensajes</h2>
                <ul class="space-y-2 text-sm">
                    @foreach($mensajes as $m)
                        <li class="border-b border-slate-100 pb-2">
                            <span class="font-mono text-xs text-slate-400">{{ $m->created_at?->format('d/m H:i') }}</span>
                            <span class="rounded bg-slate-100 px-1 text-[10px]">{{ $m->direccion }}</span>
                            {{ \Illuminate\Support\Str::limit($m->body ?: $m->respuesta, 120) }}
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    @endif
</div>
@endsection
