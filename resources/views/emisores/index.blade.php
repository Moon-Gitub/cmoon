@extends('layouts.app')

@section('titulo', 'Emisores AFIP')

@section('contenido')
    <div class="mb-4 rounded-xl border border-slate-200 bg-white p-4 text-sm text-slate-600 shadow-sm">
        Cada emisor es un CUIT habilitado para facturar. Necesita el <strong>certificado AFIP</strong> (.crt y .key
        generados para el servicio <code class="rounded bg-slate-100 px-1">wsfe</code>) y al menos un <strong>punto de venta</strong>
        dado de alta en AFIP como "Web Services".
    </div>

    @can('emisores.gestionar')
        <details class="mb-4 rounded-xl border border-slate-200 bg-white shadow-sm" {{ $errors->any() ? 'open' : '' }}>
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-indigo-700">+ Agregar emisor</summary>
            <form method="POST" action="{{ route('emisores.store') }}" class="space-y-4 border-t border-slate-100 p-5">
                @csrf
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Razón social *</label>
                        <input type="text" name="razon_social" value="{{ old('razon_social') }}" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('razon_social')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">CUIT *</label>
                        <input type="text" name="cuit" value="{{ old('cuit') }}" required placeholder="30123456789"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @error('cuit')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Condición IVA *</label>
                        <select name="condicion_iva" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="RESPONSABLE_INSCRIPTO">Responsable Inscripto (A/B)</option>
                            <option value="MONOTRIBUTO" {{ old('condicion_iva') === 'MONOTRIBUTO' ? 'selected' : '' }}>Monotributo (C)</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 gap-4 sm:grid-cols-3">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Ingresos brutos</label>
                        <input type="text" name="ingresos_brutos" value="{{ old('ingresos_brutos') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Inicio actividades</label>
                        <input type="date" name="inicio_actividades" value="{{ old('inicio_actividades') }}"
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Entorno *</label>
                        <select name="entorno" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="homologacion">Homologación (pruebas)</option>
                            <option value="produccion">Producción</option>
                        </select>
                    </div>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Domicilio fiscal</label>
                    <input type="text" name="domicilio" value="{{ old('domicilio') }}"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <input type="hidden" name="activo" value="1">
                <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Crear emisor
                </button>
            </form>
        </details>
    @endcan

    <div class="space-y-4">
        @forelse ($emisores as $emisor)
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="text-base font-bold">{{ $emisor->razon_social }}</h2>
                        <p class="text-sm text-slate-500">
                            CUIT {{ $emisor->cuit }} ·
                            {{ $emisor->condicion_iva === 'MONOTRIBUTO' ? 'Monotributo (Factura C)' : 'Resp. Inscripto (Factura A/B)' }}
                        </p>
                    </div>
                    <div class="flex items-center gap-2">
                        @if ($emisor->esProduccion())
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Producción</span>
                        @else
                            <span class="rounded-full bg-amber-50 px-2.5 py-0.5 text-xs font-medium text-amber-700">Homologación</span>
                        @endif
                        @if ($emisor->tieneCertificado())
                            <span class="rounded-full bg-emerald-50 px-2.5 py-0.5 text-xs font-medium text-emerald-700">Certificado OK</span>
                        @else
                            <span class="rounded-full bg-red-50 px-2.5 py-0.5 text-xs font-medium text-red-600">Sin certificado</span>
                        @endif
                    </div>
                </div>

                @can('emisores.gestionar')
                    <div class="mt-4 grid grid-cols-1 gap-4 lg:grid-cols-2">
                        <form method="POST" action="{{ route('emisores.certificado', $emisor) }}" enctype="multipart/form-data"
                              class="rounded-lg border border-slate-200 p-4">
                            @csrf
                            <p class="mb-2 text-sm font-semibold">Certificado AFIP</p>
                            <div class="space-y-2">
                                <label class="block text-xs text-slate-500">Certificado (.crt / .pem)
                                    <input type="file" name="certificado" required class="mt-1 block w-full text-xs">
                                </label>
                                <label class="block text-xs text-slate-500">Clave privada (.key)
                                    <input type="file" name="clave_privada" required class="mt-1 block w-full text-xs">
                                </label>
                            </div>
                            @error('certificado')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            @error('clave_privada')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                            <button class="mt-3 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                Subir certificado
                            </button>
                        </form>

                        <div class="rounded-lg border border-slate-200 p-4">
                            <p class="mb-2 text-sm font-semibold">Puntos de venta</p>
                            <div class="space-y-1.5">
                                @forelse ($emisor->puntosVenta as $pv)
                                    <div class="flex items-center justify-between rounded-lg bg-slate-50 px-3 py-1.5 text-sm">
                                        <span><span class="font-mono font-semibold">{{ str_pad($pv->numero, 4, '0', STR_PAD_LEFT) }}</span>
                                            <span class="text-slate-500">{{ $pv->descripcion }}</span></span>
                                        <form method="POST" action="{{ route('emisores.punto-venta.eliminar', [$emisor, $pv]) }}"
                                              onsubmit="return confirm('¿Eliminar el punto de venta {{ $pv->numero }}?')">
                                            @csrf @method('DELETE')
                                            <button class="text-xs text-red-500 hover:text-red-700">Eliminar</button>
                                        </form>
                                    </div>
                                @empty
                                    <p class="text-xs text-slate-400">Sin puntos de venta.</p>
                                @endforelse
                            </div>
                            <form method="POST" action="{{ route('emisores.punto-venta', $emisor) }}" class="mt-3 flex gap-2">
                                @csrf
                                <input type="number" name="numero" min="1" max="99999" required placeholder="Nº"
                                       class="w-20 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <input type="text" name="descripcion" placeholder="Descripción"
                                       class="flex-1 rounded-lg border border-slate-300 px-2 py-1.5 text-sm">
                                <button class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50">
                                    Agregar
                                </button>
                            </form>
                            @error('numero')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                        </div>
                    </div>
                @endcan
            </div>
        @empty
            <div class="rounded-xl border border-slate-200 bg-white p-10 text-center text-slate-400 shadow-sm">
                No hay emisores configurados. Agregá el primero para poder facturar.
            </div>
        @endforelse
    </div>
@endsection
