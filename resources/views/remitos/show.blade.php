@extends('layouts.app')

@section('titulo', 'Remito #'.$remito->numero)

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Ítem</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($remito->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $item->descripcion }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                </table>
            </div>
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Datos</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Estado</dt>
                        <dd><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ $remito->estado }}</span></dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $remito->fecha->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cliente</dt><dd class="font-medium">{{ $remito->cliente?->nombre ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Presupuesto</dt>
                        <dd class="font-medium">
                            @if ($remito->presupuesto)
                                <a href="{{ route('presupuestos.show', $remito->presupuesto) }}" class="text-indigo-600 hover:underline">
                                    #{{ $remito->presupuesto->numero }}
                                </a>
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Venta</dt>
                        <dd class="font-medium">
                            @if ($remito->venta)
                                @can('ventas.ver')
                                    <a href="{{ route('ventas.show', $remito->venta) }}" class="text-indigo-600 hover:underline">
                                        #{{ str_pad($remito->venta->numero, 6, '0', STR_PAD_LEFT) }}
                                    </a>
                                @else
                                    #{{ str_pad($remito->venta->numero, 6, '0', STR_PAD_LEFT) }}
                                @endcan
                            @else
                                —
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Sucursal</dt><dd class="font-medium">{{ $remito->sucursal?->nombre ?? '—' }}</dd></div>
                </dl>
                @if ($remito->observaciones)
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $remito->observaciones }}</p>
                @endif
            </div>

            @if ($remito->estado === 'emitido')
                <form method="POST" action="{{ route('remitos.entregar', $remito) }}"
                      onsubmit="return confirm('¿Marcar remito como entregado?')">
                    @csrf
                    <button class="w-full rounded-xl bg-emerald-600 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                        Marcar entregado
                    </button>
                </form>
            @endif

            @if (! $remito->venta_id && $remito->estado !== 'anulado')
                @canany(['remitos.gestionar', 'pos.vender'])
                    <form method="POST" action="{{ route('remitos.facturar', $remito) }}"
                          onsubmit="return confirm('¿Convertir este remito en venta?')">
                        @csrf
                        <button class="w-full rounded-xl bg-indigo-600 py-2.5 text-sm font-bold text-white hover:bg-indigo-700">
                            @can('facturacion.emitir')
                                Convertir a venta / Facturar
                            @else
                                Convertir a venta
                            @endcan
                        </button>
                    </form>
                @endcanany
            @elseif ($remito->venta_id)
                @can('ventas.ver')
                    <a href="{{ route('ventas.show', $remito->venta_id) }}"
                       class="block w-full rounded-xl border border-indigo-200 bg-indigo-50 py-2.5 text-center text-sm font-semibold text-indigo-700 hover:bg-indigo-100">
                        Ver venta / Facturar
                    </a>
                @endcan
            @endif

            <a href="{{ route('remitos.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver</a>
        </div>
    </div>
@endsection
