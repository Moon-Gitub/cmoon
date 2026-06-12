@extends('layouts.app')

@section('titulo', 'Mi perfil')

@section('contenido')
    <div class="max-w-xl space-y-6">
        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold">Datos de la cuenta</h2>
            <dl class="space-y-2 text-sm">
                <div class="flex justify-between"><dt class="text-slate-500">Nombre</dt><dd class="font-medium">{{ auth()->user()->name }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Usuario</dt><dd class="font-medium">{{ auth()->user()->usuario }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Email</dt><dd class="font-medium">{{ auth()->user()->email }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Rol</dt><dd class="font-medium">{{ auth()->user()->getRoleNames()->first() }}</dd></div>
                <div class="flex justify-between"><dt class="text-slate-500">Sucursal</dt><dd class="font-medium">{{ auth()->user()->sucursal?->nombre ?? '—' }}</dd></div>
            </dl>
        </div>

        <form method="POST" action="{{ route('perfil.password') }}"
              class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            @csrf @method('PUT')
            <h2 class="text-base font-semibold">Cambiar contraseña</h2>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Contraseña actual</label>
                <input type="password" name="password_actual" required autocomplete="current-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('password_actual')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Contraseña nueva</label>
                <input type="password" name="password" required autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('password')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>

            <div>
                <label class="mb-1 block text-sm font-medium text-slate-700">Confirmar contraseña nueva</label>
                <input type="password" name="password_confirmation" required autocomplete="new-password"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            </div>

            <button type="submit"
                    class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Actualizar contraseña
            </button>
        </form>
    </div>
@endsection
