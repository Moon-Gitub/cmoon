@extends('layouts.app')

@section('titulo', 'Venta #'.str_pad($venta->numero, 6, '0', STR_PAD_LEFT))

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="space-y-4 lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Producto</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Precio unit.</th>
                            <th class="px-4 py-3 text-right">IVA %</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($venta->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $item->descripcion }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-3 text-right">$ {{ number_format((float) $item->precio_unitario, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right text-slate-500">{{ rtrim(rtrim(number_format((float) $item->alicuota_iva, 2, ',', ''), '0'), ',') }}</td>
                                <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="4" class="px-4 py-2 text-right text-slate-600">Subtotal</td>
                            <td class="px-4 py-2 text-right font-semibold">$ {{ number_format((float) $venta->subtotal, 2, ',', '.') }}</td>
                        </tr>
                        @if ((float) $venta->descuento > 0)
                            <tr>
                                <td colspan="4" class="px-4 py-2 text-right text-slate-600">Descuento</td>
                                <td class="px-4 py-2 text-right font-semibold text-red-600">- $ {{ number_format((float) $venta->descuento, 2, ',', '.') }}</td>
                            </tr>
                        @endif
                        <tr>
                            <td colspan="4" class="px-4 py-3 text-right text-base font-bold">TOTAL</td>
                            <td class="px-4 py-3 text-right text-base font-bold text-indigo-600">$ {{ number_format((float) $venta->total, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Pagos</h2>
                <div class="space-y-2">
                    @foreach ($venta->pagos as $pago)
                        <div class="flex items-center justify-between rounded-lg bg-slate-50 px-4 py-2 text-sm">
                            <span>{{ $pago->medioPago->nombre }}</span>
                            <span class="font-semibold">$ {{ number_format((float) $pago->importe, 2, ',', '.') }}</span>
                        </div>
                    @endforeach
                </div>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Datos</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Estado</dt>
                        <dd>
                            @if ($venta->estado === 'completada')
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Completada</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Anulada</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $venta->fecha->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Sucursal</dt><dd class="font-medium">{{ $venta->sucursal->nombre }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Vendedor</dt><dd class="font-medium">{{ $venta->vendedor->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cliente</dt><dd class="font-medium">{{ $venta->cliente?->nombre ?? 'Consumidor final' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Origen</dt><dd class="font-medium uppercase">{{ $venta->origen }}</dd></div>
                </dl>

                @if ($venta->estado === 'anulada')
                    <div class="mt-4 rounded-lg bg-red-50 p-3 text-sm text-red-700">
                        <p class="font-semibold">Anulada el {{ $venta->anulada_at->format('d/m/Y H:i') }}</p>
                        <p>Por: {{ $venta->anuladaPor?->name }}</p>
                        <p>Motivo: {{ $venta->motivo_anulacion }}</p>
                    </div>
                @endif
            </div>

            <a href="{{ route('ventas.ticket', $venta) }}" target="_blank"
               class="block w-full rounded-xl border border-slate-300 bg-white py-2.5 text-center text-sm font-medium hover:bg-slate-50">
                Ver ticket
            </a>

            @if ($venta->estado === 'completada')
                @can('ventas.anular')
                    <form method="POST" action="{{ route('ventas.anular', $venta) }}"
                          onsubmit="return confirm('¿Anular esta venta? Se repone el stock y se revierte la cta. cte.')"
                          class="space-y-2 rounded-xl border border-red-200 bg-red-50 p-4">
                        @csrf
                        <p class="text-sm font-semibold text-red-700">Anular venta</p>
                        <input type="text" name="motivo" placeholder="Motivo de anulación" required
                               class="w-full rounded-lg border border-red-200 px-3 py-2 text-sm focus:border-red-400 focus:outline-none">
                        @error('motivo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                        <button class="w-full rounded-lg bg-red-600 py-2 text-sm font-semibold text-white hover:bg-red-700">
                            Anular venta
                        </button>
                    </form>
                @endcan
            @endif

            <a href="{{ route('ventas.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a ventas</a>
        </div>
    </div>
@endsection
