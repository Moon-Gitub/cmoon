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
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Cotización dólar</h2>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Cotización U$S → $</label>
                    <input type="number" step="0.01" min="0" name="cotizacion_dolar"
                           value="{{ old('cotizacion_dolar', $empresa->cotizacion_dolar ?? 0) }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <p class="mt-1 text-xs text-slate-400">Se usa al cargar productos con precio de compra en dólares.</p>
                    @error('cotizacion_dolar')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
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

        <div class="border-t border-slate-100 pt-4">
            <h2 class="mb-1 text-sm font-semibold uppercase tracking-wider text-slate-500">Catálogo PDF por categoría</h2>
            <p class="mb-3 text-xs text-slate-500">
                Igual que en el sistema anterior: un PDF compartible con la lista de precios de cada categoría.
                Podés subir un fondo a medida (recomendado A4 vertical) y un logo aparte.
            </p>
            <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Fondo del PDF</label>
                    <div class="flex items-start gap-3">
                        @if ($empresa->catalogo_fondo_path)
                            <img src="{{ asset('storage/'.$empresa->catalogo_fondo_path) }}" alt="Fondo"
                                 class="h-20 w-14 rounded border border-slate-200 object-cover">
                        @endif
                        <input type="file" name="catalogo_fondo" accept="image/*" class="block w-full text-sm">
                    </div>
                    <p class="mt-1 text-xs text-slate-400">JPG/PNG, máx. 5 MB. Si no hay fondo, se usa un fondo oscuro.</p>
                    @error('catalogo_fondo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Logo del catálogo</label>
                    <div class="flex items-center gap-3">
                        @if ($empresa->catalogo_logo_path || $empresa->logo_path)
                            <img src="{{ asset('storage/'.($empresa->catalogo_logo_path ?: $empresa->logo_path)) }}" alt="Logo catálogo"
                                 class="h-12 w-12 rounded-lg border border-slate-200 object-contain">
                        @endif
                        <input type="file" name="catalogo_logo" accept="image/*" class="block w-full text-sm">
                    </div>
                    <p class="mt-1 text-xs text-slate-400">Opcional. Si no se carga, usa el logo general de la empresa.</p>
                    @error('catalogo_logo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Color del título</label>
                    <input type="color" name="catalogo_color_titulo"
                           value="{{ old('catalogo_color_titulo', $empresa->catalogo_color_titulo ?? '#909e23') }}"
                           class="h-10 w-full cursor-pointer rounded-lg border border-slate-300">
                    @error('catalogo_color_titulo')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Color del texto</label>
                    <input type="color" name="catalogo_color_texto"
                           value="{{ old('catalogo_color_texto', $empresa->catalogo_color_texto ?? '#f1f0ec') }}"
                           class="h-10 w-full cursor-pointer rounded-lg border border-slate-300">
                    @error('catalogo_color_texto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
            </div>
            @php
                $token = $empresa->catalogo_share_token;
                $ejemplo = $token
                    ? route('catalogo.categoria.publico', ['token' => $token, 'categoria' => 1])
                    : null;
            @endphp
            @if ($ejemplo)
                <p class="mt-3 rounded-lg bg-slate-50 px-3 py-2 text-xs text-slate-600">
                    Link público (ejemplo): <code class="break-all">{{ preg_replace('#/categoria/\d+#', '/categoria/{id}', $ejemplo) }}</code>
                    — desde Categorías podés copiar el de cada una.
                </p>
            @endif
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
