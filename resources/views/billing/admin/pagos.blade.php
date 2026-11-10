@extends('billing.admin.layout')
@section('titulo', 'Pagos')
@php use App\Billing\Models\LegacyDetalle; use App\Billing\Support\Money; @endphp

@section('contenido')
    <p class="mb-3 text-sm text-slate-500">Incluye los pagos de la cuenta anterior y los que entran por el sistema nuevo. Un pago no se lista dos veces.</p>
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-2">Fecha</th><th>Cliente</th><th>Medio</th><th>Recibo</th><th>Referencia</th><th class="px-4 text-right">Importe</th>
                </tr>
            </thead>
            @forelse ($pagos as $p)
                @php $d = $detalles[LegacyDetalle::idDesdeReferencia($p->referencia)] ?? null; @endphp
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 whitespace-nowrap">{{ $p->fecha->format('d/m/Y') }}</td>
                    <td>
                        @if ($p->customer)
                            <a class="text-sky-800 hover:underline" href="{{ route('billing.admin.cliente', $p->customer) }}">{{ $p->customer->nombre }}</a>
                        @else — @endif
                    </td>
                    <td>{{ $d->metodo_pago ?? ($p->tipo === 'legacy_pago' ? 'cuenta anterior' : $p->tipo) }}</td>
                    <td>{{ $d->numero_recibo ?? '—' }}</td>
                    <td class="font-mono text-xs">{{ $d->referencia_mp ?? \Illuminate\Support\Str::limit($p->referencia, 24) }}</td>
                    <td class="px-4 text-right font-medium text-emerald-700">{{ Money::formato(abs($p->importe_centavos)) }}</td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-slate-500" colspan="6">No hay pagos.</td></tr>
            @endforelse
        </table>
    </div>
    <div class="mt-4">{{ $pagos->links() }}</div>
@endsection
