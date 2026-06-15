@extends('layouts.app')

@php($esNuevo = ! $cliente->exists)

@section('titulo', $esNuevo ? 'Nuevo cliente' : "Editar: {$cliente->nombre}")

@section('contenido')
    <form method="POST"
          action="{{ $esNuevo ? route('clientes.store') : route('clientes.update', $cliente) }}"
          class="max-w-3xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @unless($esNuevo) @method('PUT') @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div class="sm:col-span-2">
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre / razón social *</label>
                <input type="text" name="nombre" value="{{ old('nombre', $cliente->nombre) }}" required autofocus
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Condición IVA *</label>
                <select name="condicion_iva" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @foreach (['CONSUMIDOR_FINAL' => 'Consumidor final', 'RESPONSABLE_INSCRIPTO' => 'Responsable Inscripto', 'MONOTRIBUTO' => 'Monotributo', 'EXENTO' => 'Exento'] as $valor => $texto)
                        <option value="{{ $valor }}" {{ old('condicion_iva', $cliente->condicion_iva) === $valor ? 'selected' : '' }}>{{ $texto }}</option>
                    @endforeach
                </select>
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Tipo de documento *</label>
                <select name="tipo_documento" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    @foreach (['DNI', 'CUIT', 'CUIL', 'OTRO'] as $tipo)
                        <option value="{{ $tipo }}" {{ old('tipo_documento', $cliente->tipo_documento) === $tipo ? 'selected' : '' }}>{{ $tipo }}</option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Número de documento</label>
                <input type="text" name="documento" value="{{ old('documento', $cliente->documento) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono', $cliente->telefono) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" value="{{ old('email', $cliente->email) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Domicilio</label>
                <input type="text" name="domicilio" value="{{ old('domicilio', $cliente->domicilio) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Localidad</label>
                <input type="text" name="localidad" value="{{ old('localidad', $cliente->localidad) }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Lista de precios</label>
                <select name="lista_precio_id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">General (precio de venta)</option>
                    @foreach ($listas as $lista)
                        <option value="{{ $lista->id }}"
                            {{ (string) old('lista_precio_id', $cliente->lista_precio_id) === (string) $lista->id ? 'selected' : '' }}>
                            {{ $lista->nombre }} ({{ (float) $lista->porcentaje >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $lista->porcentaje, 2, ',', '.'), '0'), ',') }}%)
                        </option>
                    @endforeach
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Límite de crédito cta. cte.</label>
                <input type="number" step="0.01" min="0" name="limite_credito"
                       value="{{ old('limite_credito', $cliente->limite_credito) }}" placeholder="Sin límite"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Vendedor asignado</label>
                <select name="vendedor_id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">Sin asignar (todos los vendedores)</option>
                    @foreach ($vendedores as $v)
                        <option value="{{ $v->id }}" {{ (string) old('vendedor_id', $cliente->vendedor_id) === (string) $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end pb-2">
                <label class="flex items-center gap-2 text-sm text-slate-700">
                    <input type="checkbox" name="activo" value="1"
                           {{ old('activo', $esNuevo ? true : $cliente->activo) ? 'checked' : '' }}
                           class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
                    Cliente activo
                </label>
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Observaciones</label>
            <textarea name="observaciones" rows="2"
                      class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">{{ old('observaciones', $cliente->observaciones) }}</textarea>
        </div>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $esNuevo ? 'Crear cliente' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('clientes.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
@endsection
