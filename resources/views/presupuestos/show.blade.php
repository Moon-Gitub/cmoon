@extends('layouts.app')

@section('titulo', 'Presupuesto #'.str_pad($presupuesto->numero, 6, '0', STR_PAD_LEFT))

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Ítem</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Precio unit.</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($presupuesto->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $item->descripcion }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-3 text-right">$ {{ number_format((float) $item->precio_unitario, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-base font-bold">TOTAL</td>
                            <td class="px-4 py-3 text-right text-base font-bold text-indigo-600">$ {{ number_format((float) $presupuesto->total, 2, ',', '.') }}</td>
                        </tr>
                    </tfoot>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Datos</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Estado</dt>
                        <dd>@include('presupuestos.partials.estado-badge', ['presupuesto' => $presupuesto])</dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $presupuesto->fecha->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cliente</dt><dd class="font-medium">{{ $presupuesto->cliente?->nombre ?? 'Consumidor final' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Válido hasta</dt><dd class="font-medium">{{ $presupuesto->valido_hasta?->format('d/m/Y') ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Creado por</dt><dd class="font-medium">{{ $presupuesto->usuario->name }}</dd></div>
                    @if ($presupuesto->venta)
                        <div class="flex justify-between"><dt class="text-slate-500">Venta</dt>
                            <dd><a href="{{ route('ventas.show', $presupuesto->venta) }}" class="font-medium text-indigo-600 hover:underline">
                                #{{ str_pad($presupuesto->venta->numero, 6, '0', STR_PAD_LEFT) }}</a></dd>
                        </div>
                    @endif
                </dl>
                @if ($presupuesto->observaciones)
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600 whitespace-pre-line">{{ $presupuesto->observaciones }}</p>
                @endif
            </div>

            @if ($presupuesto->estado === 'pendiente_aprobacion')
                @can('presupuestos.aprobar')
                    <form method="POST" action="{{ route('presupuestos.aprobar', $presupuesto) }}">
                        @csrf
                        <button class="w-full rounded-xl bg-emerald-600 py-3 text-center text-sm font-bold text-white hover:bg-emerald-700">
                            Aprobar pedido
                        </button>
                    </form>
                    <form method="POST" action="{{ route('presupuestos.rechazar', $presupuesto) }}" class="space-y-2">
                        @csrf
                        <textarea name="motivo" rows="2" placeholder="Motivo del rechazo (opcional)"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"></textarea>
                        <button class="w-full rounded-xl border border-red-200 bg-red-50 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100">
                            Rechazar pedido
                        </button>
                    </form>
                @endcan
            @endif

            @if (in_array($presupuesto->estado, ['pendiente', 'aprobado']))
                @can('pos.vender')
                    <a href="{{ route('pos', ['presupuesto' => $presupuesto->id]) }}"
                       class="block w-full rounded-xl bg-indigo-600 py-3 text-center text-sm font-bold text-white hover:bg-indigo-700">
                        Convertir en venta (abrir en POS)
                    </a>
                @endcan
                @can('presupuestos.gestionar')
                    <form method="POST" action="{{ route('presupuestos.anular', $presupuesto) }}"
                          onsubmit="return confirm('¿Anular este presupuesto?')">
                        @csrf
                        <button class="w-full rounded-xl border border-red-200 bg-red-50 py-2.5 text-sm font-semibold text-red-600 hover:bg-red-100">
                            Anular presupuesto
                        </button>
                    </form>
                @endcan
            @endif

            <button onclick="window.print()"
                    class="w-full rounded-xl border border-slate-300 bg-white py-2.5 text-sm font-medium hover:bg-slate-50">
                Imprimir
            </button>

            <a href="{{ route('presupuestos.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver</a>
        </div>
    </div>
@endsection
