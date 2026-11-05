@extends('billing.admin.layout')
@section('titulo', 'Clientes')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    <form class="mb-4 flex gap-2">
        <input name="q" value="{{ $q }}" placeholder="Buscar por nombre, dominio o CUIT" class="w-full max-w-md rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <button class="rounded-lg bg-slate-900 px-4 py-2 text-sm text-white">Buscar</button>
    </form>

    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-2">Cliente</th><th>Estado</th><th>Modo</th><th class="text-right">Saldo</th><th class="px-4 text-right">Acciones</th></tr>
            </thead>
            <tbody>
                @foreach ($clientes as $row)
                    @php
                        $c = $row['c'];
                        $wa = \App\Billing\Services\DunningService::celularArgentino((string) $c->telefono);
                        $texto = \App\Billing\Services\DunningService::texto($c, 'vencido', $row['estado']);
                    @endphp
                    <tr class="border-t border-slate-100 {{ $c->estado === 'baja' ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2"><a href="{{ route('billing.admin.cliente', $c) }}" class="font-medium hover:underline">{{ $c->nombre }}</a>
                            <div class="text-xs text-slate-500">{{ $c->dominio }}</div></td>
                        <td>@include('billing.admin._nivel', ['nivel' => $row['estado']['nivel']])</td>
                        <td class="text-xs">{{ $c->modo_cobro === 'bloqueo' ? 'Bloquea' : 'Solo aviso' }}</td>
                        <td class="text-right font-medium">{{ Money::formato($row['saldo']) }}</td>
                        <td class="space-x-2 px-4 text-right text-xs whitespace-nowrap">
                            <button type="button" class="text-sky-700 hover:underline" onclick="navigator.clipboard.writeText('{{ $c->payUrl() }}'); this.textContent='¡Copiado!'">Copiar link</button>
                            @if ($wa && $row['saldo'] > 0)
                                <a target="_blank" rel="noopener" class="text-emerald-700 hover:underline" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($texto) }}">WhatsApp</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
