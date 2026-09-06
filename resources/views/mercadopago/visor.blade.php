@extends('layouts.app')

@section('titulo', 'Mercado Pago QR')

@section('contenido')
    @unless ($configurado)
        <div class="mb-4 rounded-xl border border-amber-200 bg-amber-50 p-4 text-sm text-amber-900">
            <p class="font-semibold">Mercado Pago no está configurado</p>
            <p class="mt-1">Completá <code class="rounded bg-amber-100 px-1">MERCADOPAGO_ACCESS_TOKEN</code>,
                <code class="rounded bg-amber-100 px-1">MERCADOPAGO_USER_ID</code> y
                <code class="rounded bg-amber-100 px-1">MERCADOPAGO_EXTERNAL_POS_ID</code> en el servidor.</p>
        </div>
    @else
        <div class="mb-4 rounded-xl border border-emerald-200 bg-emerald-50 p-4 text-sm text-emerald-800">
            Credenciales MP detectadas. El cobro QR está habilitado en el POS.
        </div>
    @endunless

    @if ($puedeListar && is_iterable($pagosMp))
        <div class="mb-6 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Pagos Mercado Pago</h2>
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">ID</th>
                        <th class="px-4 py-3">Estado</th>
                        <th class="px-4 py-3">Referencia</th>
                        <th class="px-4 py-3 text-right">Importe</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @foreach ($pagosMp as $pago)
                        @php
                            $row = is_array($pago) ? $pago : (array) $pago;
                        @endphp
                        <tr class="hover:bg-slate-50">
                            <td class="px-4 py-3 font-mono text-xs">{{ $row['id'] ?? $row['payment_id'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['status'] ?? $row['estado'] ?? '—' }}</td>
                            <td class="px-4 py-3">{{ $row['external_reference'] ?? $row['referencia'] ?? '—' }}</td>
                            <td class="px-4 py-3 text-right font-semibold">
                                $ {{ number_format((float) ($row['transaction_amount'] ?? $row['importe'] ?? 0), 2, ',', '.') }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <h2 class="border-b border-slate-100 px-4 py-3 text-sm font-semibold uppercase tracking-wider text-slate-500">
            Ventas recientes con medio QR
        </h2>
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Venta</th>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Medio</th>
                    <th class="px-4 py-3 text-right">Total</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($ventasQr as $venta)
                    @php
                        $pagoQr = $venta->pagos->first(fn ($p) => $p->medioPago?->tipo === 'qr');
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-mono text-xs text-indigo-600">
                            @can('ventas.ver')
                                <a href="{{ route('ventas.show', $venta) }}" class="hover:underline">
                                    #{{ str_pad($venta->numero, 6, '0', STR_PAD_LEFT) }}
                                </a>
                            @else
                                #{{ str_pad($venta->numero, 6, '0', STR_PAD_LEFT) }}
                            @endcan
                        </td>
                        <td class="px-4 py-3">{{ $venta->fecha->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</td>
                        <td class="px-4 py-3">{{ $pagoQr?->medioPago?->nombre ?? 'QR' }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format($venta->totalConSigno(), 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">No hay ventas con medio QR.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
