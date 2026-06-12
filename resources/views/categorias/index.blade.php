@extends('layouts.app')

@section('titulo', 'Categorías')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        @can('categorias.gestionar')
            <form method="POST" action="{{ route('categorias.store') }}"
                  class="h-fit space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <h2 class="text-base font-semibold">Nueva categoría</h2>
                <div>
                    <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Nombre de la categoría" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <button class="w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Crear categoría
                </button>
            </form>
        @endcan

        <div class="lg:col-span-2">
            <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
                <table class="w-full text-sm">
                    <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="px-4 py-3">Nombre</th>
                            <th class="px-4 py-3">Productos</th>
                            <th class="px-4 py-3">Estado</th>
                            <th class="px-4 py-3 text-right">Acciones</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($categorias as $cat)
                            <tr class="hover:bg-slate-50">
                                <td class="px-4 py-2">
                                    @can('categorias.gestionar')
                                        <form method="POST" action="{{ route('categorias.update', $cat) }}" class="flex items-center gap-2">
                                            @csrf @method('PUT')
                                            <input type="text" name="nombre" value="{{ $cat->nombre }}"
                                                   class="w-full max-w-[220px] rounded-lg border border-transparent px-2 py-1 text-sm hover:border-slate-300 focus:border-indigo-500 focus:outline-none">
                                            <input type="hidden" name="activa" value="{{ $cat->activa ? 1 : 0 }}">
                                            <button class="rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" title="Guardar nombre">✓</button>
                                        </form>
                                    @else
                                        {{ $cat->nombre }}
                                    @endcan
                                </td>
                                <td class="px-4 py-2 text-slate-600">{{ $cat->productos_count }}</td>
                                <td class="px-4 py-2">
                                    @if ($cat->activa)
                                        <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                                    @else
                                        <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                                    @endif
                                </td>
                                <td class="px-4 py-2 text-right">
                                    @can('categorias.gestionar')
                                        <div class="flex items-center justify-end gap-2">
                                            <form method="POST" action="{{ route('categorias.update', $cat) }}">
                                                @csrf @method('PUT')
                                                <input type="hidden" name="nombre" value="{{ $cat->nombre }}">
                                                <input type="hidden" name="activa" value="{{ $cat->activa ? 0 : 1 }}">
                                                <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">
                                                    {{ $cat->activa ? 'Desactivar' : 'Activar' }}
                                                </button>
                                            </form>
                                            <form method="POST" action="{{ route('categorias.destroy', $cat) }}"
                                                  onsubmit="return confirm('¿Eliminar la categoría {{ $cat->nombre }}?')">
                                                @csrf @method('DELETE')
                                                <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                            </form>
                                        </div>
                                    @endcan
                                </td>
                            </tr>
                        @empty
                            <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No hay categorías.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>
        </div>
    </div>
@endsection
