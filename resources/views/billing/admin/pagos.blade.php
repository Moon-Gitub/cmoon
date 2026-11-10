@extends('billing.admin.layout')
@section('titulo', 'Pagos')
@php use App\Billing\Models\LegacyDetalle; use App\Billing\Support\Money; @endphp

@section('contenido')
    <div class="mb-6">
        <h1 class="font-display text-3xl font-medium tracking-tight text-white">Pagos</h1>
        <p class="mt-1 text-base text-[var(--md-ink-mute)]">Cuenta anterior y sistema nuevo. Un pago no se lista dos veces.</p>
    </div>

    <div class="md-panel overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Fecha</th><th>Cliente</th><th>Medio</th><th>Recibo</th><th>Referencia</th><th class="text-right">Importe</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($pagos as $p)
                @php $d = $detalles[LegacyDetalle::idDesdeReferencia($p->referencia)] ?? null; @endphp
                <tr>
                    <td class="whitespace-nowrap">{{ $p->fecha->format('d/m/Y') }}</td>
                    <td>
                        @if ($p->customer)
                            <a href="{{ route('billing.admin.cliente', $p->customer) }}">{{ $p->customer->nombre }}</a>
                        @else — @endif
                    </td>
                    <td>{{ $d->metodo_pago ?? ($p->tipo === 'legacy_pago' ? 'cuenta anterior' : $p->tipo) }}</td>
                    <td>{{ $d->numero_recibo ?? '—' }}</td>
                    <td class="font-mono text-[0.95rem]">{{ $d->referencia_mp ?? \Illuminate\Support\Str::limit($p->referencia, 24) }}</td>
                    <td class="md-money text-right text-lg text-[var(--md-ok)]">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                </tr>
            @empty
                <tr><td class="py-8 text-[var(--md-text-mute)]" colspan="6">No hay pagos.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
    <div class="mt-5 text-white">{{ $pagos->links() }}</div>
@endsection
