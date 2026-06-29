@extends('layouts.app')

@section('titulo', 'Datos de la empresa')

@section('contenido')
    <form method="POST" action="{{ route('empresa.update') }}" enctype="multipart/form-data"
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

        <div class="border-t border-slate-100 pt-4">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Retenciones IIBB (SIRCAR)</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                <label class="flex items-start gap-2 rounded-lg border border-slate-200 p-3 sm:col-span-2 lg:col-span-4">
                    <input type="checkbox" name="agente_retencion_iibb" value="1"
                           {{ old('agente_retencion_iibb', $empresa->agente_retencion_iibb) ? 'checked' : '' }}
                           class="mt-1 rounded border-slate-300">
                    <span>
                        <span class="block text-sm font-medium text-slate-700">Agente de retención de Ingresos Brutos</span>
                        <span class="text-xs text-slate-500">Habilita retenciones en pagos a proveedores y exportación SIRCAR.</span>
                    </span>
                </label>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Jurisdicción CM</label>
                    <input type="number" name="codigo_jurisdiccion_iibb" min="1"
                           value="{{ old('codigo_jurisdiccion_iibb', $empresa->codigo_jurisdiccion_iibb ?? 913) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">913 = Mendoza</p>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Tipo régimen (campo 10 TXT)</label>
                    <input type="number" name="tipo_regimen_retencion_default" min="1"
                           value="{{ old('tipo_regimen_retencion_default', $empresa->tipo_regimen_retencion_default ?? 101) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Próximo nº recibo</label>
                    <input type="number" name="proximo_numero_recibo" min="1"
                           value="{{ old('proximo_numero_recibo', $empresa->proximo_numero_recibo ?? 1) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
        </div>

        <div class="border-t border-slate-100 pt-4">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Personalización visual</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Color del sistema *</label>
                    <input type="color" name="color_primario"
                           value="{{ old('color_primario', $empresa->color_primario ?? '#4f46e5') }}"
                           class="h-10 w-full cursor-pointer rounded-lg border border-slate-300">
                    <p class="mt-1 text-xs text-slate-400">Cambia el color de botones, menú y acentos.</p>
                    @error('color_primario')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Logo</label>
                    <div class="flex items-center gap-4">
                        @if ($empresa->logo_path)
                            <img src="{{ asset('storage/'.$empresa->logo_path) }}" alt="Logo"
                                 class="h-12 w-12 rounded-lg border border-slate-200 object-contain">
                        @endif
                        <input type="file" name="logo" accept="image/*" class="block w-full text-sm">
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Se muestra en el menú, tickets y facturas. PNG/JPG, máx. 2 MB.</p>
                    @error('logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
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
