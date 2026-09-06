@extends('layouts.app')

@section('titulo', 'Depósitos')

@section('contenido')
    @can('depositos.gestionar')
        <form method="POST" action="{{ route('depositos.store') }}"
              class="mb-6 grid grid-cols-1 gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm sm:grid-cols-2 lg:grid-cols-4">
            @csrf
            <div class="lg:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Nombre *</label>
                <input type="text" name="nombre" required value="{{ old('nombre') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Sucursal (opcional)</label>
                <select name="sucursal_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">— Independiente —</option>
                    @foreach ($sucursales as $suc)
                        <option value="{{ $suc->id }}" @selected(old('sucursal_id') == $suc->id)>{{ $suc->nombre }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end">
                <button class="w-full rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    + Crear depósito
                </button>
            </div>
        </form>
    @endcan

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Sucursal</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($depositos as $dep)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $dep->nombre }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $dep->sucursal?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($dep->activo)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('depositos.gestionar')
                                <form method="POST" action="{{ route('depositos.update', $dep) }}" class="inline">
                                    @csrf
                                    @method('PUT')
                                    <input type="hidden" name="activo" value="{{ $dep->activo ? 0 : 1 }}">
                                    <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">
                                        {{ $dep->activo ? 'Desactivar' : 'Activar' }}
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No hay depósitos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
