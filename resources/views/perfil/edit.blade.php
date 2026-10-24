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

        <div class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="text-base font-semibold">Doble factor (TOTP)</h2>
            <p class="text-sm text-slate-600">Opcional. Recomendado para administradores. Usá Google Authenticator, Aegis o similar.</p>

            @if (session('2fa_recovery'))
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-3 text-sm">
                    <p class="font-medium text-amber-900">Códigos de recuperación (se muestran una sola vez)</p>
                    <ul class="mt-2 grid grid-cols-2 gap-1 font-mono text-xs">
                        @foreach (session('2fa_recovery') as $code)
                            <li>{{ $code }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            @if (auth()->user()->tieneDosFactores())
                <p class="text-sm text-emerald-700">Activo desde {{ auth()->user()->two_factor_confirmed_at?->format('d/m/Y H:i') }}.</p>
                <form method="POST" action="{{ route('perfil.2fa.disable') }}" class="space-y-3">
                    @csrf @method('DELETE')
                    <input type="password" name="password_actual" required placeholder="Contraseña actual"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <input type="text" name="codigo" required placeholder="Código de 6 dígitos"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('codigo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="rounded-lg bg-red-600 px-4 py-2 text-sm font-semibold text-white hover:bg-red-700">Desactivar</button>
                </form>
            @elseif ($pendingSecret)
                <div class="mx-auto max-w-[180px] [&>svg]:h-full [&>svg]:w-full">{!! $qrSvg !!}</div>
                <p class="break-all text-center font-mono text-xs text-slate-500">{{ $pendingSecret }}</p>
                <form method="POST" action="{{ route('perfil.2fa.confirm') }}" class="space-y-3">
                    @csrf
                    <input type="text" name="codigo" required inputmode="numeric" placeholder="Código de 6 dígitos"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('codigo')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Confirmar activación</button>
                </form>
            @else
                <form method="POST" action="{{ route('perfil.2fa.enable') }}">
                    @csrf
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Generar código QR</button>
                </form>
            @endif
        </div>
    </div>
@endsection
