@extends('billing.admin.layout')
@section('titulo', 'Tablero')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    <div class="grid grid-cols-2 gap-4 lg:grid-cols-5">
        @foreach ([
            ['Abono mensual (MRR)', Money::formato($mrr)],
            ['Facturado '.$periodo, Money::formato($facturado)],
            ['Cobrado '.$periodo, Money::formato($cobrado)],
            ['Tasa de cobro', $tasa === null ? '—' : $tasa.' %'],
            ['Deuda total', Money::formato($deudaTotal)],
        ] as [$t, $v])
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <p class="text-xs text-slate-500">{{ $t }}</p>
                <p class="mt-1 text-xl font-semibold">{{ $v }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Antigüedad de la deuda</h2>
            <dl class="mt-3 space-y-2 text-sm">
                @foreach (['0_30' => '0 a 30 días', '31_60' => '31 a 60 días', '61_90' => '61 a 90 días', 'mas_90' => 'Más de 90 días'] as $k => $t)
                    <div class="flex justify-between"><dt>{{ $t }}</dt><dd class="font-medium">{{ Money::formato($aging[$k]) }}</dd></div>
                @endforeach
            </dl>
            <h2 class="mt-6 font-semibold">Clientes por estado</h2>
            <div class="mt-3 flex flex-wrap gap-2">
                @foreach ($niveles as $n => $cant)
                    <span class="text-sm">@include('billing.admin._nivel', ['nivel' => $n]) {{ $cant }}</span>
                @endforeach
            </div>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm lg:col-span-2">
            <h2 class="font-semibold">Mayores deudores</h2>
            <table class="mt-3 w-full text-sm">
                @foreach ($deudores as $d)
                    <tr class="border-t border-slate-100">
                        <td class="py-2"><a class="hover:underline" href="{{ route('billing.admin.cliente', $d['c']) }}">{{ $d['c']->nombre }}</a></td>
                        <td>@include('billing.admin._nivel', ['nivel' => $d['nivel']])</td>
                        <td class="text-right font-medium">{{ Money::formato($d['saldo']) }}</td>
                    </tr>
                @endforeach
            </table>
        </div>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-2">
        <div class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Últimos pagos</h2>
            <table class="mt-3 w-full text-sm">
                @forelse ($ultimosPagos as $p)
                    <tr class="border-t border-slate-100">
                        <td class="py-2">{{ $p->fecha->format('d/m/Y') }}</td>
                        <td>{{ $p->customer?->nombre ?? '—' }}</td>
                        <td class="text-slate-500">{{ $p->tipo === 'legacy_pago' ? 'cuenta anterior' : $p->tipo }}</td>
                        <td class="text-right font-medium">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                    </tr>
                @empty
                    <tr><td class="py-2 text-slate-500">Todavía no hay pagos.</td></tr>
                @endforelse
            </table>
        </div>

        <div class="rounded-xl bg-white p-4 shadow-sm">
            <h2 class="font-semibold">Salud del sistema</h2>
            <ul class="mt-3 space-y-1 text-sm">
                <li>Cuotas generadas en {{ $periodo }}: <b>{{ $cuotasMes }}</b></li>
                <li>Webhooks sin procesar (+30 min): <b class="{{ $webhooksConError ? 'text-red-600' : '' }}">{{ $webhooksConError }}</b></li>
                <li>Última conciliación:
                    @if ($ultimaConciliacion)
                        <b class="{{ $ultimaConciliacion->estado === 'ok' ? 'text-emerald-700' : 'text-red-600' }}">{{ strtoupper($ultimaConciliacion->estado) }}</b>
                        ({{ $ultimaConciliacion->created_at->format('d/m H:i') }})
                    @else — @endif
                </li>
            </ul>
            <h3 class="mt-4 text-sm font-semibold">Alertas recientes</h3>
            <ul class="mt-2 space-y-1 text-xs text-slate-600">
                @forelse ($alertas as $a)
                    <li>{{ $a->created_at->format('d/m H:i') }} · <b>{{ $a->accion }}</b> {{ \Illuminate\Support\Str::limit(json_encode($a->detalle, JSON_UNESCAPED_UNICODE), 120) }}</li>
                @empty
                    <li>Sin alertas.</li>
                @endforelse
            </ul>
        </div>
    </div>
@endsection
