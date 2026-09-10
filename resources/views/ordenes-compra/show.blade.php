@extends('layouts.app')

@section('titulo', 'OC #'.$orden->numero)

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-4">
            @can('ordenes-compra.gestionar')
            <form method="POST" action="{{ route('ordenes-compra.recibir', $orden) }}">
                @csrf
            @endcan
                <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                    <table class="w-full text-sm">
                        <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                            <tr>
                                <th class="px-4 py-3">Ítem</th>
                                <th class="px-4 py-3 text-right">Pedido</th>
                                <th class="px-4 py-3 text-right">Recibido</th>
                                <th class="px-4 py-3 text-right">Pendiente</th>
                                <th class="px-4 py-3 text-right">Recibir ahora</th>
                                <th class="px-4 py-3 text-right">P. unit.</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @foreach ($orden->items as $i => $item)
                                @php $pend = max(0, (float) $item->cantidad - (float) $item->cantidad_recibida); @endphp
                                <tr>
                                    <td class="px-4 py-3 font-medium">{{ $item->descripcion }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format((float) $item->cantidad_recibida, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-4 py-3 text-right">{{ rtrim(rtrim(number_format($pend, 3, ',', '.'), '0'), ',') }}</td>
                                    <td class="px-4 py-3 text-right">
                                        @can('ordenes-compra.gestionar')
                                            <input type="hidden" name="items[{{ $i }}][id]" value="{{ $item->id }}">
                                            @if ($pend > 0 && ! in_array($orden->estado, ['anulada', 'recibida'], true))
                                                <input type="number" step="any" min="0" max="{{ $pend }}" name="items[{{ $i }}][cantidad_recibir]"
                                                       value="{{ $pend }}"
                                                       class="w-24 rounded-lg border border-slate-300 px-2 py-1 text-right text-sm">
                                            @else
                                                <input type="hidden" name="items[{{ $i }}][cantidad_recibir]" value="0">
                                                <span class="text-slate-400">—</span>
                                            @endif
                                        @else
                                            <span class="text-slate-400">—</span>
                                        @endcan
                                    </td>
                                    <td class="px-4 py-3 text-right">$ {{ number_format((float) $item->precio_unitario, 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                        <tfoot class="bg-slate-50">
                            <tr>
                                <td colspan="5" class="px-4 py-3 text-right text-base font-bold">TOTAL</td>
                                <td class="px-4 py-3 text-right text-base font-bold text-indigo-600">$ {{ number_format((float) $orden->total, 2, ',', '.') }}</td>
                            </tr>
                        </tfoot>
                    </table>
                </div>
                @can('ordenes-compra.gestionar')
                    @if (! in_array($orden->estado, ['anulada', 'recibida'], true))
                        <button class="mt-3 rounded-xl bg-emerald-600 px-5 py-2.5 text-sm font-semibold text-white hover:bg-emerald-700">
                            Registrar recepción
                        </button>
                    @endif
            </form>
                @endcan
        </div>

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Datos</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Estado</dt>
                        <dd><span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium">{{ $orden->estado }}</span></dd>
                    </div>
                    <div class="flex justify-between"><dt class="text-slate-500">Fecha</dt><dd class="font-medium">{{ $orden->fecha->format('d/m/Y') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Proveedor</dt><dd class="font-medium">{{ $orden->proveedor->razon_social }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Sucursal</dt><dd class="font-medium">{{ $orden->sucursal->nombre }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Usuario</dt><dd class="font-medium">{{ $orden->usuario->name }}</dd></div>
                </dl>
                @if ($orden->observaciones)
                    <p class="mt-3 rounded-lg bg-slate-50 p-3 text-sm text-slate-600">{{ $orden->observaciones }}</p>
                @endif
            </div>
            <a href="{{ route('ordenes-compra.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver</a>
        </div>
    </div>
@endsection
