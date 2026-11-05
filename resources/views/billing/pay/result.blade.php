@extends('billing.layout')

@section('titulo', 'Resultado del pago')

@php
    use App\Billing\Support\Money;
    $acreditado = $pago && $pago->acreditado_at;
    $pendiente = $pago && in_array($pago->estado, ['pending', 'in_process', 'authorized'], true);
    $rechazado = $pago && in_array($pago->estado, ['rejected', 'cancelled'], true);
    $destino = $customer->posUrl() ?? route('billing.pagar', $customer->pay_token);
@endphp

@section('contenido')
    <div class="rounded-2xl bg-white p-6 text-center shadow-sm" id="resultado"
         data-estado-url="{{ route('billing.pagar.estado', [$customer->pay_token, 'payment_id' => $paymentId]) }}"
         data-destino="{{ $destino }}"
         data-acreditado="{{ $acreditado ? '1' : '0' }}"
         data-verificar="{{ $paymentId && ! $acreditado && ! $rechazado ? '1' : '0' }}">

        @if ($acreditado)
            <p class="text-5xl">✅</p>
            <h1 class="mt-3 text-xl font-semibold">¡Pago acreditado!</h1>
            <p class="mt-2 text-slate-600">Recibimos {{ Money::formato($pago->importe_centavos) }}. Operación #{{ $pago->external_id }}.</p>
            <p class="mt-1 text-sm text-slate-500">Saldo actual: {{ Money::formato(max(0, $estado['saldo'])) }}</p>
        @elseif ($rechazado)
            <p class="text-5xl">❌</p>
            <h1 class="mt-3 text-xl font-semibold">El pago no se completó</h1>
            <p class="mt-2 text-slate-600">Mercado Pago lo rechazó. Podés intentarlo con otro medio.</p>
            <a href="{{ route('billing.pagar', $customer->pay_token) }}" class="mt-6 inline-block rounded-xl bg-[#009ee3] px-6 py-3 font-semibold text-white">Intentar de nuevo</a>
        @elseif ($pendiente)
            <p class="text-5xl">⏳</p>
            <h1 class="mt-3 text-xl font-semibold">Pago pendiente</h1>
            <p class="mt-2 text-slate-600">Cuando Mercado Pago lo apruebe se acredita solo. Si pagaste en efectivo, puede demorar hasta 48 h hábiles.</p>
        @elseif ($paymentId)
            <p class="text-5xl" id="spinner">🔄</p>
            <h1 class="mt-3 text-xl font-semibold" id="titulo">Verificando tu pago…</h1>
            <p class="mt-2 text-slate-600" id="detalle">Estamos confirmando con Mercado Pago. No cierres esta pantalla.</p>
        @else
            <p class="text-5xl">ℹ️</p>
            <h1 class="mt-3 text-xl font-semibold">No recibimos un pago</h1>
            <p class="mt-2 text-slate-600">Si ya pagaste, se acreditará automáticamente en unos minutos.</p>
        @endif

        <a href="{{ $destino }}" class="mt-6 block w-full rounded-xl border border-slate-300 px-4 py-3 font-medium">Volver al sistema</a>
    </div>
@endsection

@push('scripts')
<script>
(() => {
    const el = document.getElementById('resultado');
    const destino = el.dataset.destino;
    const irAlSistema = () => window.location.replace(destino);

    if (el.dataset.acreditado === '1') {
        setTimeout(irAlSistema, 6000);
        return;
    }
    if (el.dataset.verificar !== '1') return;

    let intentos = 0;
    const consultar = async () => {
        intentos++;
        try {
            const r = await fetch(el.dataset.estadoUrl, { headers: { Accept: 'application/json' } });
            const d = await r.json();
            if (d.pago && d.pago.acreditado) {
                document.getElementById('spinner').textContent = '✅';
                document.getElementById('titulo').textContent = '¡Pago acreditado!';
                document.getElementById('detalle').textContent = 'Te llevamos de vuelta al sistema…';
                setTimeout(irAlSistema, 3000);
                return;
            }
        } catch (e) {}
        if (intentos < 10) {
            setTimeout(consultar, 3000);
        } else {
            document.getElementById('titulo').textContent = 'Seguimos verificando';
            document.getElementById('detalle').textContent = 'Tu pago se acreditará automáticamente. Ya podés volver al sistema.';
        }
    };
    setTimeout(consultar, 1500);
})();
</script>
@endpush
