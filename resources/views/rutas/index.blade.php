@extends('layouts.app')

@section('titulo', 'Rutas de visita')

@section('contenido')
    <div class="grid grid-cols-1 gap-6 xl:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <h2 class="mb-4 text-sm font-semibold uppercase tracking-wider text-slate-500">Nueva ruta</h2>
            <form method="POST" action="{{ route('rutas.store') }}" class="space-y-3">
                @csrf
                <div>
                    <label class="mb-1 block text-sm font-medium">Nombre</label>
                    <input type="text" name="nombre" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Vendedor</label>
                    <select name="user_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($vendedores as $v)
                            <option value="{{ $v->id }}">{{ $v->name }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Día (opcional)</label>
                    <select name="dia_semana" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Cualquier día</option>
                        @foreach (['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $i => $dia)
                            <option value="{{ $i }}">{{ $dia }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium">Clientes (orden de visita)</label>
                    <select name="cliente_ids[]" multiple size="8" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        @foreach ($clientes as $c)
                            <option value="{{ $c->id }}">{{ $c->nombre }}</option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-500">Ctrl+clic para seleccionar varios.</p>
                </div>
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Crear ruta</button>
            </form>
        </div>

        <div class="space-y-4">
            @forelse ($rutas as $ruta)
                <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                    <form method="POST" action="{{ route('rutas.update', $ruta) }}" class="space-y-3">
                        @csrf @method('PUT')
                        <div class="flex items-start justify-between gap-2">
                            <input type="text" name="nombre" value="{{ $ruta->nombre }}" required
                                   class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm font-semibold">
                            <label class="flex items-center gap-1 text-xs"><input type="checkbox" name="activa" value="1" {{ $ruta->activa ? 'checked' : '' }}> Activa</label>
                        </div>
                        <select name="user_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($vendedores as $v)
                                <option value="{{ $v->id }}" {{ $ruta->user_id === $v->id ? 'selected' : '' }}>{{ $v->name }}</option>
                            @endforeach
                        </select>
                        <select name="dia_semana" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="">Cualquier día</option>
                            @foreach (['Domingo','Lunes','Martes','Miércoles','Jueves','Viernes','Sábado'] as $i => $dia)
                                <option value="{{ $i }}" {{ (string) $ruta->dia_semana === (string) $i ? 'selected' : '' }}>{{ $dia }}</option>
                            @endforeach
                        </select>
                        <select name="cliente_ids[]" multiple size="6" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            @foreach ($clientes as $c)
                                <option value="{{ $c->id }}" {{ $ruta->clientes->contains($c->id) ? 'selected' : '' }}>{{ $c->nombre }}</option>
                            @endforeach
                        </select>
                        <p class="text-xs text-slate-500">{{ $ruta->clientes->count() }} cliente(s) en ruta</p>
                        <div class="flex gap-2">
                            <button class="rounded-lg bg-indigo-600 px-3 py-1.5 text-xs font-semibold text-white">Guardar</button>
                        </div>
                    </form>
                    <form method="POST" action="{{ route('rutas.destroy', $ruta) }}" class="mt-2" onsubmit="return confirm('¿Eliminar ruta?')">
                        @csrf @method('DELETE')
                        <button class="text-xs text-red-600 hover:underline">Eliminar ruta</button>
                    </form>
                </div>
            @empty
                <p class="text-slate-400">No hay rutas configuradas.</p>
            @endforelse
        </div>
    </div>
@endsection
