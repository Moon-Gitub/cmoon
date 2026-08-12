@extends('layouts.app')

@section('titulo', 'n8n')

@section('contenido')
<div class="space-y-6">
    <div>
        <h1 class="text-2xl font-bold">Automatizaciones n8n</h1>
        <p class="mt-1 text-slate-500">POSMoon dispara webhooks a tus flujos. n8n puede llamar de vuelta con el secret.</p>
    </div>

    <div class="rounded-lg border border-slate-200 bg-white p-4 text-sm text-slate-600">
        Entrada (n8n → POSMoon): <code class="rounded bg-slate-100 px-1">{{ $inboundUrl }}</code>
        · Header <code class="rounded bg-slate-100 px-1">X-N8N-Secret</code>
        · JSON <code class="rounded bg-slate-100 px-1">empresa_id</code>, <code class="rounded bg-slate-100 px-1">accion</code>
        (ping, productos.buscar, whatsapp.enviar)
        · Docs: <code class="rounded bg-slate-100 px-1">docs/21-n8n-ia.md</code>
    </div>

    <form method="POST" action="{{ route('n8n.store') }}" class="space-y-4 rounded-xl border border-slate-200 bg-white p-6">
        @csrf
        <div class="grid gap-4 md:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium">Header Auth (nombre)</label>
                <input name="header_name" value="{{ old('header_name', $integracion->header_name ?? 'X-N8N-Auth') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium">Header Auth (valor)</label>
                <input name="header_value" type="password" autocomplete="off" placeholder="{{ $integracion?->header_value ? '•••• (sin cambiar)' : '' }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-sm font-medium">Secret inbound (n8n → POSMoon) *</label>
                <input name="inbound_secret" value="{{ old('inbound_secret', $integracion->inbound_secret ?? '') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
        </div>

        <h2 class="pt-2 text-sm font-semibold">Production URL de cada Webhook node</h2>
        <div class="grid gap-3">
            @foreach ($eventos as $key => $label)
                <div>
                    <label class="mb-1 block text-xs text-slate-500">{{ $label }} <code>{{ $key }}</code></label>
                    <input name="webhooks[{{ $key }}]" type="url"
                           value="{{ old('webhooks.'.$key, data_get($integracion?->webhooks, $key)) }}"
                           placeholder="https://n8n.tudominio.com/webhook/..."
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            @endforeach
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white">Guardar</button>
    </form>

    @if($integracion)
        <form method="POST" action="{{ route('n8n.probar') }}" class="flex flex-wrap items-end gap-2">
            @csrf
            <div>
                <label class="mb-1 block text-xs text-slate-500">Probar evento</label>
                <select name="evento" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach ($eventos as $key => $label)
                        <option value="{{ $key }}">{{ $label }}</option>
                    @endforeach
                </select>
            </div>
            <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm">Enviar ping</button>
        </form>

        @if($logs->isNotEmpty())
            <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                        <tr>
                            <th class="px-3 py-2">Fecha</th>
                            <th class="px-3 py-2">Dir</th>
                            <th class="px-3 py-2">Evento</th>
                            <th class="px-3 py-2">HTTP</th>
                            <th class="px-3 py-2">Estado</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($logs as $log)
                            <tr>
                                <td class="px-3 py-2 text-xs text-slate-500">{{ $log->created_at?->format('d/m H:i') }}</td>
                                <td class="px-3 py-2">{{ $log->direccion }}</td>
                                <td class="px-3 py-2 font-mono text-xs">{{ $log->evento }}</td>
                                <td class="px-3 py-2">{{ $log->http_status ?? '—' }}</td>
                                <td class="px-3 py-2">{{ $log->status }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        @endif
    @endif
</div>
@endsection
