@extends('layouts.app')

@php($esNueva = ! $sucursal->exists)

@section('titulo', $esNueva ? 'Nueva sucursal' : "Editar: {$sucursal->nombre}")

@section('contenido')
    <form method="POST"
          action="{{ $esNueva ? route('sucursales.store') : route('sucursales.update', $sucursal) }}"
          class="max-w-xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @unless($esNueva) @method('PUT') @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $sucursal->nombre) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Código</label>
                <input type="text" name="codigo" value="{{ old('codigo', $sucursal->codigo) }}" placeholder="Ej: CC"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Domicilio</label>
            <input type="text" name="domicilio" value="{{ old('domicilio', $sucursal->domicilio) }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
            <input type="text" name="telefono" value="{{ old('telefono', $sucursal->telefono) }}"
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="activa" value="1"
                   {{ old('activa', $esNueva ? true : $sucursal->activa) ? 'checked' : '' }}
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Sucursal activa
        </label>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $esNueva ? 'Crear sucursal' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('sucursales.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
@endsection
