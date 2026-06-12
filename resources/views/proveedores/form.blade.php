@extends('layouts.app')

@php($esNuevo = ! $proveedor->exists)

@section('titulo', $esNuevo ? 'Nuevo proveedor' : "Editar: {$proveedor->razon_social}")

@section('contenido')
    <form method="POST"
          action="{{ $esNuevo ? route('proveedores.store') : route('proveedores.update', $proveedor) }}"
          class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @unless($esNuevo) @method('PUT') @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Razón social *</label>
                <input type="text" name="razon_social" value="{{ old('razon_social', $proveedor->razon_social) }}" required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('razon_social')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">CUIT</label>
                <input type="text" name="cuit" value="{{ old('cuit', $proveedor->cuit) }}" placeholder="30123456789"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Condición IVA *</label>
                <select name="condicion_iva" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @foreach (['RESPONSABLE_INSCRIPTO' => 'Responsable Inscripto', 'MONOTRIBUTO' => 'Monotributo', 'EXENTO' => 'Exento'] as $valor => $texto)
                        <option value="{{ $valor }}" {{ old('condicion_iva', $proveedor->condicion_iva) === $valor ? 'selected' : '' }}>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $proveedor->telefono) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $proveedor->email) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Domicilio</label>
                <input type="text" name="domicilio" value="{{ old('domicilio', $proveedor->domicilio) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Localidad</label>
                <input type="text" name="localidad" value="{{ old('localidad', $proveedor->localidad) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Alícuota retención IIBB %</label>
                <input type="number" step="0.01" min="0" max="100" name="alicuota_retencion_iibb"
                       value="{{ old('alicuota_retencion_iibb', $proveedor->alicuota_retencion_iibb) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                <p class="mt-1 text-xs text-slate-500">Para agente de retención SIRCAR (0 = no retener)</p>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
            <textarea name="observaciones" rows="2"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('observaciones', $proveedor->observaciones) }}</textarea>
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="activo" value="1"
                   {{ old('activo', $esNuevo ? true : $proveedor->activo) ? 'checked' : '' }}
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Proveedor activo
        </label>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $esNuevo ? 'Crear proveedor' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('proveedores.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
@endsection
