@extends('billing.layout')

@php
    use App\Billing\Support\Money;
    $colores = [
        'al_dia' => 'border-[var(--md-ok)] text-[var(--md-ink)]',
        'pendiente' => 'border-[var(--md-ink-mute)] text-[var(--md-ink)]',
        'vencido' => 'border-[var(--md-warn)] text-[var(--md-ink)]',
        'por_suspender' => 'border-[var(--md-warn)] text-[var(--md-ink)]',
        'moroso' => 'border-[var(--md-danger)] text-[var(--md-ink)]',
        'suspendido' => 'border-[var(--md-danger)] text-[var(--md-ink)]',
    ];
@endphp

@section('contenido')
    <div class="border border-[var(--md-slate-line)] bg-[var(--md-slate-mid)] p-6">
        <p class="text-sm text-[var(--md-ink-mute)]">Cliente</p>
        <h1 class="font-display text-xl font-light tracking-wide">{{ $customer->nombre }}</h1>

        @if ($estado['nivel'] === 'al_dia')
            <div class="mt-6 border px-4 py-5 text-center {{ $colores['al_dia'] }}">
                <p class="mt-2 font-semibold">Estás al día</p>
                <p class="mt-1 text-sm text-[var(--md-ink-mute)]">No tenés pagos pendientes. Gracias.</p>
            </div>
        @else
            <div class="mt-4 border px-4 py-3 text-sm {{ $colores[$estado['nivel']] ?? '' }}">
                {{ $estado['mensaje'] }}
            </div>

            <div class="mt-6">
                <p class="text-sm text-[var(--md-ink-mute)]">Total a pagar</p>
                <p class="md-money font-display text-4xl font-light tracking-tight">{{ Money::formato($cotTotal['total']) }}</p>
                @if ($cotTotal['recargo'] > 0)
                    <p class="mt-1 text-sm text-[var(--md-ink-mute)]">Incluye recargo por mora de {{ Money::formato($cotTotal['recargo']) }}</p>
                @endif
            </div>

            <a href="{{ route('billing.pagar.ir', [$customer->pay_token, 'modo' => 'total']) }}"
               class="mt-6 flex w-full items-center justify-center bg-[#009ee3] px-4 py-4 text-lg font-semibold text-white hover:bg-[#0089c7]">
                Pagar con Mercado Pago
            </a>
            <p class="mt-2 text-center text-xs text-[var(--md-ink-mute)]">Tarjeta, débito, dinero en cuenta o efectivo (Rapipago / Pago Fácil)</p>

            @if ($cotPeriodo['total'] > 0 && $cotPeriodo['total'] < $cotTotal['total'])
                <a href="{{ route('billing.pagar.ir', [$customer->pay_token, 'modo' => 'periodo']) }}"
                   class="mt-3 flex w-full items-center justify-center border border-[var(--md-slate-line)] px-4 py-3 text-sm font-medium hover:bg-[var(--md-slate-deep)]">
                    Pagar solo este mes ({{ Money::formato($cotPeriodo['total']) }})
                </a>
            @endif

            @if (count($pendientes))
                <details class="mt-6 border border-[var(--md-slate-line)]">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-medium">Ver detalle de lo adeudado</summary>
                    <ul class="divide-y divide-[var(--md-slate-line)] px-4 pb-2 text-sm">
                        @foreach ($pendientes as $p)
                            <li class="flex justify-between py-2">
                                <span>{{ $p['descripcion'] }} <span class="text-[var(--md-ink-mute)]">· {{ \Carbon\Carbon::parse($p['fecha'])->format('d/m/Y') }}</span></span>
                                <span class="md-money font-medium">{{ Money::formato($p['pendiente']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (! empty($transferencia['alias']) || ! empty($transferencia['cbu']))
                <div class="mt-6 bg-[var(--md-slate-deep)] p-4 text-sm">
                    <p class="font-medium">¿Preferís transferir?</p>
                    @if ($transferencia['alias'])<p class="mt-1">Alias: <span class="font-mono font-semibold">{{ $transferencia['alias'] }}</span></p>@endif
                    @if ($transferencia['cbu'])<p>CBU: <span class="font-mono">{{ $transferencia['cbu'] }}</span></p>@endif
                    @if ($transferencia['titular'])<p>Titular: {{ $transferencia['titular'] }}</p>@endif
                    <p class="mt-2 text-[var(--md-ink-mute)]">Enviá el comprobante a Moon para que lo acreditemos.</p>
                </div>
            @endif
        @endif
    </div>
@endsection
