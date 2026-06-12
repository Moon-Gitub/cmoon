@extends('layouts.app')

@section('titulo', 'Listas de precio')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        @can('listas-precio.gestionar')
            <form method="POST" action="{{ route('listas-precio.store') }}"
                  class="h-fit space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="text-base font-semibold">Nueva lista</h2>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Nombre</label>
                    <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Ej: Mayorista" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Porcentaje sobre precio de venta</label>
                    <input type="number" step="0.01" name="porcentaje" value="{{ old('porcentaje', 0) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <p class="mt-1 text-xs text-slate-500">10 = +10% de recargo · -5 = 5% de descuento</p>
                    @error('porcentaje')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <input type="hidden" name="activa" value="1">
                <button class="w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Crear lista
                </button>
            </form>
        @endcan

        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3 text-right">Ajuste</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($listas as $lista)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-3 font-medium">{{ $lista->nombre }}</td>
                                <td class="px-4 py-3 text-right">
                                    <span class="{{ (float) $lista->porcentaje >= 0 ? 'text-emerald-700' : 'text-red-600' }} font-semibold">
                                        {{ (float) $lista->porcentaje >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $lista->porcentaje, 2, ',', '.'), '0'), ',') }}%
                                    </span>
                                </td>
                                <td class="px-4 py-3">
                                    @if ($lista->activa)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                                    @endif
                                </td>
                                <td class="px-4 py-3 text-right">
                                    @can('listas-precio.gestionar')
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('listas-precio.update', $lista) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="nombre" value="{{ $lista->nombre }}">
                                                <input type="hidden" name="porcentaje" value="{{ $lista->porcentaje }}">
                                                <input type="hidden" name="activa" value="{{ $lista->activa ? 0 : 1 }}">
                                                <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">
                                                    {{ $lista->activa ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('listas-precio.destroy', $lista) }}"
                                                  onsubmit="return confirm('¿Eliminar la lista {{ $lista->nombre }}?')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No hay listas de precio.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
