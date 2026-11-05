@extends('billing.admin.layout')
@section('titulo', 'Eventos')

@section('contenido')
    <div class="grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Conciliaciones</h2>
            <table class="mt-3 w-full text-sm">
                @forelse ($conciliaciones as $r)
                    <tr class="border-t border-slate-100">
                        <td class="py-1.5">{{ $r->created_at->format('d/m H:i') }}</td>
                        <td class="{{ $r->estado === 'ok' ? 'text-emerald-700' : 'text-red-600' }} font-semibold">{{ strtoupper($r->estado) }}</td>
                        <td>{{ $r->revisados }} revisados · {{ $r->acreditados }} acreditados · {{ count($r->alertas ?? []) }} alertas</td>
                    </tr>
                @empty <tr><td class="text-slate-500">Sin conciliaciones todavía.</td></tr> @endforelse
            </table>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Webhooks de Mercado Pago</h2>
            <table class="mt-3 w-full text-xs">
                @forelse ($webhooks as $w)
                    <tr class="border-t border-slate-100">
                        <td class="py-1.5">{{ $w->created_at->format('d/m H:i:s') }}</td>
                        <td>{{ $w->topic }}</td>
                        <td class="font-mono">{{ $w->resource_id }}</td>
                        <td>{{ $w->firma_valida ? 'firma ok' : 'FIRMA INVÁLIDA' }}</td>
                        <td>{{ $w->procesado_at ? 'procesado' : 'pendiente ('.$w->intentos.')' }}</td>
                        <td class="text-slate-500">{{ \Illuminate\Support\Str::limit($w->error, 60) }}</td>
                    </tr>
                @empty <tr><td class="text-slate-500">Sin notificaciones.</td></tr> @endforelse
            </table>
        </div>
    </div>

    <div class="mt-6 rounded-xl bg-white p-4 shadow-sm">
        <h2 class="font-semibold">Auditoría</h2>
        <table class="mt-3 w-full text-xs">
            @foreach ($auditoria as $a)
                <tr class="border-t border-slate-100 {{ in_array($a->nivel, ['alerta', 'error']) ? 'bg-red-50' : ($a->nivel === 'warning' ? 'bg-amber-50' : '') }}">
                    <td class="py-1.5 whitespace-nowrap">{{ $a->created_at->format('d/m H:i:s') }}</td>
                    <td>{{ $a->nivel }}</td>
                    <td class="font-semibold">{{ $a->accion }}</td>
                    <td>{{ $a->customer_id ? '#'.$a->customer_id : '' }}</td>
                    <td class="font-mono text-slate-600">{{ \Illuminate\Support\Str::limit(json_encode($a->detalle, JSON_UNESCAPED_UNICODE), 160) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
@endsection
