@extends('layouts.app')

@section('titulo', 'Cajas')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="space-y-4 lg:col-span-2">
            @foreach ($cajas as $caja)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <div class="flex flex-wrap items-center justify-between gap-3">
                        <div>
                            <h2 class="font-semibold">{{ $caja->nombre }}</h2>
                            <p class="text-xs text-slate-500">{{ $caja->sucursal->nombre }}</p>
                        </div>

                        @if ($caja->sesionAbierta)
                            <div class="flex items-center gap-3">
                                <div class="text-right text-xs">
                                    <p class="flex items-center gap-1.5 font-medium text-emerald-700">
                                        <span class="h-1.5 w-1.5 rounded-full bg-emerald-500"></span>
                                        Abierta por {{ $caja->sesionAbierta->usuario->name }}
                                    </p>
                                    <p class="text-slate-500">desde {{ $caja->sesionAbierta->abierta_at->format('d/m H:i') }}</p>
                                </div>
                                <a href="{{ route('cajas.sesion', $caja->sesionAbierta) }}"
                                   class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                                    Ver / cerrar
                                </a>
                            </div>
                        @else
                            @can('cajas.operar')
                                <form method="POST" action="{{ route('cajas.abrir', $caja) }}" class="flex items-center gap-2">
                                    @csrf
                                    <input type="number" step="0.01" min="0" name="monto_apertura" placeholder="Monto inicial $" required
                                           class="w-36 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                                    <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">
                                        Abrir caja
                                    </button>
                                </form>
                            @else
                                <span class="text-sm text-slate-400">Cerrada</span>
                            @endcan
                        @endif
                    </div>
                </div>
            @endforeach

            @can('cajas.gestionar')
                <form method="POST" action="{{ route('cajas.store') }}"
                      class="flex flex-wrap items-end gap-2 rounded-xl border border-dashed border-slate-300 bg-white p-5">
                    @csrf
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Nueva caja</label>
                        <input type="text" name="nombre" placeholder="Nombre" required
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    </div>
                    <div>
                        <label class="mb-1 block text-xs font-medium text-slate-500">Sucursal</label>
                        <select name="sucursal_id" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($sucursales as $suc)
                                <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <button class="rounded-lg border border-slate-300 px-4 py-2 text-sm font-medium hover:bg-slate-50">Crear</button>
                    @error('nombre')<p class="w-full text-xs text-red-600">{{ $message }}</p>@enderror
                </form>
            @endcan
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Últimos cierres</h2>
            <div class="space-y-2">
                @forelse ($sesionesPrevias as $sesion)
                    @php($diferencia = (float) $sesion->monto_cierre_declarado - (float) $sesion->monto_cierre_sistema)
                    <a href="{{ route('cajas.sesion', $sesion) }}"
                       class="block rounded-lg border border-slate-100 px-3 py-2 text-sm hover:bg-slate-50">
                        <div class="flex justify-between">
                            <span class="font-medium">{{ $sesion->caja->nombre }}</span>
                            <span class="text-xs text-slate-500">{{ $sesion->cerrada_at->format('d/m H:i') }}</span>
                        </div>
                        <div class="flex justify-between text-xs text-slate-500">
                            <span>{{ $sesion->usuario->name }}</span>
                            <span class="{{ abs($diferencia) > 0.01 ? 'font-semibold text-red-600' : 'text-emerald-600' }}">
                                {{ abs($diferencia) > 0.01 ? 'Dif: $ '.number_format($diferencia, 2, ',', '.') : 'Sin diferencia' }}
                            </span>
                        </div>
                    </a>
                @empty
                    <p class="py-4 text-center text-sm text-slate-400">Sin cierres registrados.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
