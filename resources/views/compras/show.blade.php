@extends('layouts.app')

@section('titulo', 'Compra #'.$compra->id)

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Ítem</th>
                            <th class="px-4 py-3 text-right">Cantidad</th>
                            <th class="px-4 py-3 text-right">Costo unit.</th>
                            <th class="px-4 py-3 text-right">Total</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($compra->items as $item)
                            <tr>
                                <td class="px-4 py-3 font-medium">{{ $item->descripcion }}</td>
                                <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                                <td class="px-4 py-3 text-right">$ {{ number_format((float) $item->costo_unitario, 2, ',', '.') }}</td>
                                <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $item->total, 2, ',', '.') }}</td>
                            </tr>
                        @endforeach
                    </tbody>
                    <tfoot class="bg-slate-50">
                        <tr>
                            <td colspan="3" class="px-4 py-3 text-right text-base font-bold">TOTAL</td>
                            <td class="px-4 py-3 text-right text-base font-bold text-indigo-600">$ {{ number_format((float) $compra->total, 2, ',', '.') }}</td>
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
                        <dd>
                            @if ($compra->estado === 'completada')
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Completada</span>
                            @else
                                <span class="rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Anulada</span>
                            @endif
                        </dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $compra->fecha->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Proveedor</dt><dd class="font-medium">{{ $compra->proveedor->razon_social }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Factura</dt><dd class="font-mono text-xs">{{ $compra->factura_numero ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Condición</dt><dd class="font-medium">{{ $compra->condicion === 'cuenta_corriente' ? 'Cta. corriente' : 'Contado' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Sucursal</dt><dd class="font-medium">{{ $compra->sucursal->nombre }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Cargada por</dt><dd class="font-medium">{{ $compra->usuario->name }}</dd></div>
                </dl>
                @if ($compra->observaciones)
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $compra->observaciones }}</p>
                @endif
            </div>

            @if ($compra->estado === 'completada')
                @can('compras.gestionar')
                    <form method="POST" action="{{ route('compras.anular', $compra) }}"
                          onsubmit="return confirm('¿Anular esta compra? Se revierte el stock y la cta. cte.')">
                        @csrf
                        <button class="w-full rounded-xl bg-red-600 py-2.5 text-sm font-semibold text-white hover:bg-red-700">
                            Anular compra
                        </button>
                    </form>
                @endcan
            @endif

            <a href="{{ route('compras.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a compras</a>
        </div>
    </div>
@endsection
