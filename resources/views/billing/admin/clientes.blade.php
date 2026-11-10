@extends('billing.admin.layout')
@section('titulo', 'Clientes')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    <div class="mb-7 flex flex-wrap items-end justify-between gap-5">
        <div>
            <h1 class="font-display text-[2rem] font-medium tracking-tight text-[var(--md-text)]">Clientes</h1>
            <p class="mt-1 text-[1.05rem] text-[var(--md-text-mute)]">{{ count($clientes) }} cuentas en el hub</p>
        </div>
        <form class="flex w-full max-w-xl gap-2">
            <input name="q" value="{{ $q }}" placeholder="Buscar por nombre, dominio o CUIT" class="min-w-0 flex-1">
            <button class="md-btn md-btn-primary shrink-0">Buscar</button>
        </form>
    </div>

    <div class="md-panel overflow-hidden">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Estado</th>
                    <th>Modo</th>
                    <th class="text-right">Saldo</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
                @foreach ($clientes as $row)
                    @php
                        $c = $row['c'];
                        $wa = \App\Billing\Services\DunningService::celularArgentino((string) $c->telefono);
                        $texto = \App\Billing\Services\DunningService::texto($c, 'vencido', $row['estado']);
                    @endphp
                    <tr class="{{ $c->estado === 'baja' ? 'opacity-50' : '' }}">
                        <td>
                            <a href="{{ route('billing.admin.cliente', $c) }}" class="text-[1.125rem] font-semibold text-[var(--md-text)] hover:text-[var(--md-slate)]">{{ $c->nombre }}</a>
                            @if ($c->dominio)
                                <div class="mt-1 text-[0.95rem] text-[var(--md-text-mute)]">{{ $c->dominio }}</div>
                            @endif
                        </td>
                        <td>@include('billing.admin._nivel', ['nivel' => $row['estado']['nivel']])</td>
                        <td class="text-[var(--md-text-mute)]">{{ $c->modo_cobro === 'bloqueo' ? 'Bloquea' : 'Solo aviso' }}</td>
                        <td class="md-money text-right text-[1.125rem] text-[var(--md-text)]">{{ Money::formato($row['saldo']) }}</td>
                        <td class="space-x-2 text-right whitespace-nowrap">
                            <button type="button" class="md-btn md-btn-ghost" onclick="navigator.clipboard.writeText('{{ $c->payUrl() }}'); this.textContent='Copiado'">Copiar link</button>
                            @if ($wa && $row['saldo'] > 0)
                                <a target="_blank" rel="noopener" class="md-btn md-btn-wa" href="https://wa.me/{{ $wa }}?text={{ rawurlencode($texto) }}">WhatsApp</a>
                            @endif
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
@endsection
