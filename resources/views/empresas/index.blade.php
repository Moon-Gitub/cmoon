@extends('layouts.app')

@section('titulo', 'Empresas del sistema')

@section('contenido')
    <div class="mb-4 rounded-xl border border-sky-200 bg-sky-50 p-4 text-sm text-sky-800">
        Cada empresa tiene sus propios productos, clientes, ventas, cajas y CUITs emisores.
        Los usuarios solo ven los datos de la empresa a la que pertenecen.
    </div>

    <details class="mb-4 rounded-xl border border-slate-200 bg-white shadow-sm" {{ $errors->any() ? 'open' : '' }}>
        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-indigo-700">+ Nueva empresa</summary>
        <form method="POST" action="{{ route('empresas.store') }}"
              class="grid grid-cols-1 gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Razón social *</label>
                <input type="text" name="razon_social" value="{{ old('razon_social') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('razon_social')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre de fantasía</label>
                <input type="text" name="nombre_fantasia" value="{{ old('nombre_fantasia') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">CUIT</label>
                <input type="text" name="cuit" value="{{ old('cuit') }}" placeholder="30123456789"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('cuit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Condición IVA *</label>
                <select name="condicion_iva" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="RESPONSABLE_INSCRIPTO">Responsable Inscripto</option>
                    <option value="MONOTRIBUTO">Monotributo</option>
                    <option value="EXENTO">Exento</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                <input type="email" name="email" value="{{ old('email') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                <input type="text" name="telefono" value="{{ old('telefono') }}"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
            </div>
            <div class="flex items-end sm:col-span-2 lg:col-span-3">
                <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Crear empresa
                </button>
            </div>
        </form>
    </details>

    <div class="space-y-3">
        @foreach ($empresas as $empresa)
            <details class="rounded-xl border border-slate-200 bg-white shadow-sm">
                <summary class="flex cursor-pointer items-center justify-between px-5 py-4">
                    <div class="flex items-center gap-3">
                        @if ($empresa->logo_path)
                            <img src="{{ asset('storage/'.$empresa->logo_path) }}" class="h-9 w-9 rounded-lg object-contain" alt="">
                        @else
                            <div class="flex h-9 w-9 items-center justify-center rounded-lg text-sm font-bold text-white"
                                 style="background: {{ $empresa->color_primario }}">
                                {{ strtoupper(substr($empresa->razon_social, 0, 1)) }}
                            </div>
                        @endif
                        <div>
                            <p class="font-semibold">{{ $empresa->razon_social }}
                                @unless ($empresa->activa)
                                    <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Inactiva</span>
                                @endunless
                            </p>
                            <p class="text-xs text-slate-500">
                                {{ $empresa->cuit ?? 'Sin CUIT' }} · {{ $empresa->usuarios_count }} usuario(s)
                            </p>
                        </div>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </summary>
                <form method="POST" action="{{ route('empresas.update', $empresa) }}"
                      class="grid grid-cols-1 gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-3">
                    @csrf @method('PUT')
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Razón social *</label>
                        <input type="text" name="razon_social" value="{{ $empresa->razon_social }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Nombre de fantasía</label>
                        <input type="text" name="nombre_fantasia" value="{{ $empresa->nombre_fantasia }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">CUIT</label>
                        <input type="text" name="cuit" value="{{ $empresa->cuit }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Condición IVA *</label>
                        <select name="condicion_iva" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach (['RESPONSABLE_INSCRIPTO' => 'Responsable Inscripto', 'MONOTRIBUTO' => 'Monotributo', 'EXENTO' => 'Exento'] as $valor => $texto)
                                <option value="{{ $valor }}" {{ $empresa->condicion_iva === $valor ? 'selected' : '' }}>{{ $texto }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Email</label>
                        <input type="email" name="email" value="{{ $empresa->email }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Teléfono</label>
                        <input type="text" name="telefono" value="{{ $empresa->telefono }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div class="flex items-center justify-between sm:col-span-2 lg:col-span-3">
                        <label class="flex items-center gap-2 text-sm text-slate-700">
                            <input type="checkbox" name="activa" value="1" {{ $empresa->activa ? 'checked' : '' }}
                                   class="rounded border-slate-300">
                            Empresa activa
                        </label>
                        <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                            Guardar
                        </button>
                    </div>
                </form>
            </details>
        @endforeach
    </div>
@endsection
