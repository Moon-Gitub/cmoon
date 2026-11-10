@extends('billing.admin.layout')
@section('titulo', 'Eventos')

@section('contenido')
    <h1 class="mb-6 font-display text-[2rem] font-medium tracking-tight text-[var(--md-text)]">Eventos</h1>

    <div class="grid gap-6 lg:grid-cols-2">
        <div class="md-panel p-5">
            <h2 class="text-xl font-semibold text-[var(--md-text)]">Conciliaciones</h2>
            <table class="mt-3 w-full">
                @forelse ($conciliaciones as $r)
                    <tr>
                        <td>{{ $r->created_at->format('d/m H:i') }}</td>
                        <td class="font-semibold {{ $r->estado === 'ok' ? 'text-[var(--md-ok)]' : 'text-[var(--md-danger)]' }}">{{ strtoupper($r->estado) }}</td>
                        <td>{{ $r->revisados }} revisados · {{ $r->acreditados }} acreditados · {{ count($r->alertas ?? []) }} alertas</td>
                    </tr>
                @empty
                    <tr><td class="text-[var(--md-text-mute)]">Sin conciliaciones todavía.</td></tr>
                @endforelse
            </table>
        </div>

        <div class="md-panel overflow-x-auto p-5">
            <h2 class="text-xl font-semibold text-[var(--md-text)]">Webhooks de Mercado Pago</h2>
            <table class="mt-3 w-full">
                @forelse ($webhooks as $w)
                    <tr>
                        <td class="whitespace-nowrap">{{ $w->created_at->format('d/m H:i') }}</td>
                        <td>{{ $w->topic }}</td>
                        <td class="font-mono">{{ $w->resource_id }}</td>
                        <td>{{ $w->firma_valida ? 'firma ok' : 'FIRMA INVÁLIDA' }}</td>
                        <td>{{ $w->procesado_at ? 'procesado' : 'pendiente ('.$w->intentos.')' }}</td>
                    </tr>
                @empty
                    <tr><td class="text-[var(--md-text-mute)]">Sin notificaciones.</td></tr>
                @endforelse
            </table>
        </div>
    </div>

    <div class="md-panel mt-6 overflow-x-auto p-5">
        <h2 class="text-xl font-semibold text-[var(--md-text)]">Auditoría</h2>
        <table class="mt-3 w-full">
            @foreach ($auditoria as $a)
                <tr class="{{ in_array($a->nivel, ['alerta', 'error']) ? 'bg-red-50' : ($a->nivel === 'warning' ? 'bg-amber-50' : '') }}">
                    <td class="whitespace-nowrap">{{ $a->created_at->format('d/m H:i') }}</td>
                    <td class="font-semibold">{{ $a->accion }}</td>
                    <td class="text-[var(--md-text-mute)]">{{ \Illuminate\Support\Str::limit(json_encode($a->detalle, JSON_UNESCAPED_UNICODE), 140) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
