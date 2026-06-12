@extends('layouts.app')

@section('titulo', 'Sucursales')

@section('contenido')
    <div class="mb-4 flex justify-end">
        @can('sucursales.gestionar')
            <a href="{{ route('sucursales.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + Nueva sucursal
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Código</th>
                    <th class="px-4 py-3">Domicilio</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Usuarios</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($sucursales as $suc)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $suc->nombre }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $suc->codigo ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $suc->domicilio ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $suc->telefono ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $suc->usuarios_count }}</td>
                        <td class="px-4 py-3">
                            @if ($suc->activa)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('sucursales.gestionar')
                                <div class="flex items-center justify-end gap-2">
                                    <a href="{{ route('sucursales.edit', $suc) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Editar</a>
                                    <form method="POST" action="{{ route('sucursales.destroy', $suc) }}"
                                          onsubmit="return confirm('¿Eliminar la sucursal {{ $suc->nombre }}?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                    </form>
                                </div>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No hay sucursales.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
