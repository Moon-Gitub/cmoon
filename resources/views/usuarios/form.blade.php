@extends('layouts.app')

@php($esNuevo = ! $usuarioEditado->exists)

@section('titulo', $esNuevo ? 'Nuevo usuario' : "Editar: {$usuarioEditado->name}")

@section('contenido')
    <form method="POST"
          action="{{ $esNuevo ? route('usuarios.store') : route('usuarios.update', $usuarioEditado) }}"
          class="max-w-2xl space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
        @csrf
        @unless($esNuevo) @method('PUT') @endunless

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Nombre completo *</label>
                <input type="text" name="name" value="{{ old('name', $usuarioEditado->name) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('name')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Usuario (para ingresar) *</label>
                <input type="text" name="usuario" value="{{ old('usuario', $usuarioEditado->usuario) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('usuario')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
        </div>

        <div>
            <label class="mb-1 block text-sm font-medium text-slate-700">Email *</label>
            <input type="email" name="email" value="{{ old('email', $usuarioEditado->email) }}" required
                   class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            @error('email')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">
                    Contraseña {{ $esNuevo ? '*' : '(dejar vacío para no cambiar)' }}
                </label>
                <input type="password" name="password" {{ $esNuevo ? 'required' : '' }} autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar contraseña</label>
                <input type="password" name="password_confirmation" {{ $esNuevo ? 'required' : '' }} autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>
        </div>

        <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Rol *</label>
                <select name="rol" required
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">Seleccionar rol</option>
                    @foreach ($roles as $rol)
                        <option value="{{ $rol->name }}"
                            {{ old('rol', $usuarioEditado->getRoleNames()->first()) === $rol->name ? 'selected' : '' }}>
                            {{ $rol->name }}
                        </option>
                    @endforeach
                </select>
                @error('rol')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Sucursal</label>
                <select name="sucursal_id"
                        class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                    <option value="">Sin sucursal asignada</option>
                    @foreach ($sucursales as $suc)
                        <option value="{{ $suc->id }}"
                            {{ (string) old('sucursal_id', $usuarioEditado->sucursal_id) === (string) $suc->id ? 'selected' : '' }}>
                            {{ $suc->nombre }}
                        </option>
                    @endforeach
                </select>
                @error('sucursal_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            @if (($empresas ?? collect())->isNotEmpty())
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Empresa</label>
                    <select name="empresa_id"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        @foreach ($empresas as $emp)
                            <option value="{{ $emp->id }}"
                                {{ (string) old('empresa_id', $usuarioEditado->empresa_id ?? auth()->user()->empresa_id) === (string) $emp->id ? 'selected' : '' }}>
                                {{ $emp->razon_social }}
                            </option>
                        @endforeach
                    </select>
                    <p class="mt-1 text-xs text-slate-400">El usuario solo verá los datos de esta empresa.</p>
                </div>
            @endif
        </div>

        <label class="flex items-center gap-2 text-sm text-slate-700">
            <input type="checkbox" name="activo" value="1"
                   {{ old('activo', $esNuevo ? true : $usuarioEditado->activo) ? 'checked' : '' }}
                   class="rounded border-slate-300 text-indigo-600 focus:ring-indigo-500">
            Usuario activo (puede iniciar sesión)
        </label>

        <div class="flex items-center gap-3 border-t border-slate-100 pt-4">
            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                {{ $esNuevo ? 'Crear usuario' : 'Guardar cambios' }}
            </button>
            <a href="{{ route('usuarios.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Cancelar</a>
        </div>
    </form>
@endsection
