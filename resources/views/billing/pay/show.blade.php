@extends('billing.layout')

@php
    use App\Billing\Support\Money;
    $colores = [
        'al_dia' => 'bg-emerald-50 text-emerald-800 border-emerald-200',
        'pendiente' => 'bg-sky-50 text-sky-800 border-sky-200',
        'vencido' => 'bg-amber-50 text-amber-800 border-amber-200',
        'por_suspender' => 'bg-orange-50 text-orange-800 border-orange-200',
        'moroso' => 'bg-red-50 text-red-800 border-red-200',
        'suspendido' => 'bg-red-50 text-red-800 border-red-200',
    ];
@endphp

@section('contenido')
    <div class="rounded-2xl bg-white p-6 shadow-sm">
        <p class="text-sm text-slate-500">Cliente</p>
        <h1 class="text-xl font-semibold">{{ $customer->nombre }}</h1>

        @if ($estado['nivel'] === 'al_dia')
            <div class="mt-6 rounded-xl border px-4 py-5 text-center {{ $colores['al_dia'] }}">
                <p class="text-2xl">✅</p>
                <p class="mt-2 font-semibold">Estás al día</p>
                <p class="mt-1 text-sm">No tenés pagos pendientes. ¡Gracias!</p>
            </div>
        @else
            <div class="mt-4 rounded-xl border px-4 py-3 text-sm {{ $colores[$estado['nivel']] ?? '' }}">
                {{ $estado['mensaje'] }}
            </div>

            <div class="mt-6">
                <p class="text-sm text-slate-500">Total a pagar</p>
                <p class="text-4xl font-bold tracking-tight">{{ Money::formato($cotTotal['total']) }}</p>
                @if ($cotTotal['recargo'] > 0)
                    <p class="mt-1 text-sm text-slate-500">Incluye recargo por mora de {{ Money::formato($cotTotal['recargo']) }}</p>
                @endif
            </div>

            <a href="{{ route('billing.pagar.ir', [$customer->pay_token, 'modo' => 'total']) }}"
               class="mt-6 flex w-full items-center justify-center rounded-xl bg-[#009ee3] px-4 py-4 text-lg font-semibold text-white shadow hover:bg-[#0089c7]">
                Pagar con Mercado Pago
            </a>
            <p class="mt-2 text-center text-xs text-slate-500">Tarjeta, débito, dinero en cuenta o efectivo (Rapipago / Pago Fácil)</p>

            @if ($cotPeriodo['total'] > 0 && $cotPeriodo['total'] < $cotTotal['total'])
                <a href="{{ route('billing.pagar.ir', [$customer->pay_token, 'modo' => 'periodo']) }}"
                   class="mt-3 flex w-full items-center justify-center rounded-xl border border-slate-300 px-4 py-3 text-sm font-medium hover:bg-slate-50">
                    Pagar solo este mes ({{ Money::formato($cotPeriodo['total']) }})
                </a>
            @endif

            @if (count($pendientes))
                <details class="mt-6 rounded-xl border border-slate-200">
                    <summary class="cursor-pointer px-4 py-3 text-sm font-medium">Ver detalle de lo adeudado</summary>
                    <ul class="divide-y divide-slate-100 px-4 pb-2 text-sm">
                        @foreach ($pendientes as $p)
                            <li class="flex justify-between py-2">
                                <span>{{ $p['descripcion'] }} <span class="text-slate-400">· {{ \Carbon\Carbon::parse($p['fecha'])->format('d/m/Y') }}</span></span>
                                <span class="font-medium">{{ Money::formato($p['pendiente']) }}</span>
                            </li>
                        @endforeach
                    </ul>
                </details>
            @endif

            @if (! empty($transferencia['alias']) || ! empty($transferencia['cbu']))
                <div class="mt-6 rounded-xl bg-slate-50 p-4 text-sm">
                    <p class="font-medium">¿Preferís transferir?</p>
                    @if ($transferencia['alias'])<p class="mt-1">Alias: <span class="font-mono font-semibold">{{ $transferencia['alias'] }}</span></p>@endif
                    @if ($transferencia['cbu'])<p>CBU: <span class="font-mono">{{ $transferencia['cbu'] }}</span></p>@endif
                    @if ($transferencia['titular'])<p>Titular: {{ $transferencia['titular'] }}</p>@endif
                    <p class="mt-2 text-slate-500">Enviá el comprobante a Moon para que lo acreditemos.</p>
                </div>
            @endif
        @endif
    </div>
@endsection
