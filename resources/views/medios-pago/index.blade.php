@extends('layouts.app')

@section('titulo', 'Medios de pago')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        @can('medios-pago.gestionar')
            <form method="POST" action="{{ route('medios-pago.store') }}"
                  class="h-fit space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="text-base font-semibold">Nuevo medio de pago</h2>
                <div>
                    <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Nombre" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <select name="tipo" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @foreach (['efectivo' => 'Efectivo', 'tarjeta_debito' => 'Tarjeta de débito', 'tarjeta_credito' => 'Tarjeta de crédito', 'transferencia' => 'Transferencia', 'qr' => 'QR / Billetera', 'cheque' => 'Cheque', 'cuenta_corriente' => 'Cuenta corriente', 'otro' => 'Otro'] as $valor => $texto)
                        <option value="{{ $valor }}">{{ $texto }}</option>
                    @endforeach
                </select>
                <div>
                    <input type="number" step="0.01" name="recargo_porcentaje" value="{{ old('recargo_porcentaje', 0) }}"
                           placeholder="Recargo %"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-500">Recargo o descuento aplicado al pagar (ej: 10 = +10%)</p>
                </div>
                <input type="hidden" name="activo" value="1">
                <button class="w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Crear medio de pago
                </button>
            </form>
        @endcan

        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Tipo</th>
                            <th class="px-4 py-3 text-right">Recargo</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($medios as $medio)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $medio->nombre }}</td>
                                <td class="px-4 py-3 text-xs capitalize text-slate-600">{{ str_replace('_', ' ', $medio->tipo) }}</td>
                                <td class="px-4 py-3 text-right text-slate-600">
                                    {{ (float) $medio->recargo_porcentaje != 0 ? rtrim(rtrim(number_format((float) $medio->recargo_porcentaje, 2, ',', '.'), '0'), ',').'%' : '—' }}
                                </td>
                                <td class="px-4 py-3">
                                    @if ($medio->activo)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activo</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('medios-pago.gestionar')
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('medios-pago.update', $medio) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="nombre" value="{{ $medio->nombre }}">
                                                <input type="hidden" name="tipo" value="{{ $medio->tipo }}">
                                                <input type="hidden" name="recargo_porcentaje" value="{{ $medio->recargo_porcentaje }}">
                                                <input type="hidden" name="activo" value="{{ $medio->activo ? 0 : 1 }}">
                                                <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">
                                                    {{ $medio->activo ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('medios-pago.destroy', $medio) }}"
                                                  onsubmit="return confirm('¿Eliminar {{ $medio->nombre }}?')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No hay medios de pago.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
