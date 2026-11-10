@extends('billing.admin.layout')
@section('titulo', 'Clientes')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div>
            <h1 class="font-display text-3xl font-medium tracking-tight text-white">Clientes</h1>
            <p class="mt-1 text-base text-[var(--md-ink-mute)]">{{ count($clientes) }} cuentas · buscá por nombre, dominio o CUIT</p>
        </div>
        <form class="flex w-full max-w-xl gap-2">
            <input name="q" value="{{ $q }}" placeholder="Buscar por nombre, dominio o CUIT" class="min-w-0 flex-1">
            <button class="md-btn md-btn-primary shrink-0">Buscar</button>
        </form>
    </div>

    <div class="md-panel overflow-x-auto">
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
                            <a href="{{ route('billing.admin.cliente', $c) }}" class="text-lg font-semibold text-[var(--md-text)] hover:text-[var(--md-slate-deep)]">{{ $c->nombre }}</a>
                            <div class="mt-0.5 text-[var(--md-text-mute)]">{{ $c->dominio }}</div>
                        </td>
                        <td>@include('billing.admin._nivel', ['nivel' => $row['estado']['nivel']])</td>
                        <td class="text-[var(--md-text-mute)]">{{ $c->modo_cobro === 'bloqueo' ? 'Bloquea' : 'Solo aviso' }}</td>
                        <td class="md-money text-right text-lg text-[var(--md-text)]">{{ Money::formato($row['saldo']) }}</td>
                        <td class="space-x-3 text-right whitespace-nowrap">
                            <button type="button" class="md-btn-ghost" onclick="navigator.clipboard.writeText('{{ $c->payUrl() }}'); this.textContent='Copiado'">Copiar link</button>
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
