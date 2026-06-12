@extends('layouts.app')

@section('titulo', 'Datos de la empresa')

@section('contenido')
    <form method="POST" action="{{ route('empresa.update') }}"
          class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf @method('PUT')

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Razón social *</label>
                <input type="text" name="razon_social" value="{{ old('razon_social', $empresa->razon_social) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('razon_social')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre de fantasía</label>
                <input type="text" name="nombre_fantasia" value="{{ old('nombre_fantasia', $empresa->nombre_fantasia) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">CUIT</label>
                <input type="text" name="cuit" value="{{ old('cuit', $empresa->cuit) }}" placeholder="30123456789"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('cuit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Condición frente al IVA *</label>
                <select name="condicion_iva" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @foreach (['RESPONSABLE_INSCRIPTO' => 'Responsable Inscripto', 'MONOTRIBUTO' => 'Monotributo', 'EXENTO' => 'Exento'] as $valor => $texto)
                        <option value="{{ $valor }}" {{ old('condicion_iva', $empresa->condicion_iva) === $valor ? 'selected' : '' }}>
                            {{ $texto }}
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Ingresos brutos</label>
                <input type="text" name="ingresos_brutos" value="{{ old('ingresos_brutos', $empresa->ingresos_brutos) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Inicio de actividades</label>
                <input type="date" name="inicio_actividades"
                       value="{{ old('inicio_actividades', $empresa->inicio_actividades?->format('Y-m-d')) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $empresa->telefono) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $empresa->email) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-4">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Domicilio</label>
                <input type="text" name="domicilio" value="{{ old('domicilio', $empresa->domicilio) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Localidad</label>
                <input type="text" name="localidad" value="{{ old('localidad', $empresa->localidad) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Provincia</label>
                <input type="text" name="provincia" value="{{ old('provincia', $empresa->provincia) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        @can('empresa.editar')
            <div class="border-t border-slate-100 pt-4">
                <button type="submit"
                        class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Guardar cambios
                </button>
            </div>
        @endcan
    </form>
@endsection
