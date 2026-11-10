<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Ingresar · {{ config('app.name') }}</title>
    @php($esHub = (bool) config('billing.hub_enabled'))
    @if ($esHub)
        <link rel="icon" href="{{ asset('brand/moon-mark.svg') }}" type="image/svg+xml">
    @endif
    @vite(['resources/css/app.css'])
</head>
<body class="flex min-h-screen items-center justify-center p-4 antialiased {{ $esHub ? 'md' : 'bg-gradient-to-br from-slate-900 via-indigo-950 to-slate-900' }}">

    <div class="w-full max-w-md">
        <div class="mb-8 text-center">
            @if ($esHub)
                <img src="{{ asset('brand/moon-mark.svg') }}" alt="Moon Desarrollos" class="mx-auto mb-5 h-20 w-20" width="80" height="80">
                <h1 class="font-display text-2xl font-light tracking-[0.28em] text-white">MOON</h1>
                <p class="mt-1 font-display text-xs tracking-[0.42em] text-[var(--md-ink-mute)]">DESARROLLOS</p>
                <p class="mt-4 text-base text-[var(--md-ink-mute)]">Moon Cobros · ingresá para continuar</p>
            @else
                <div class="mx-auto mb-3 flex h-14 w-14 items-center justify-center rounded-2xl bg-indigo-500 text-2xl font-bold text-white shadow-lg shadow-indigo-500/30">C</div>
                <h1 class="text-2xl font-bold text-white">POSMoon</h1>
                <p class="text-sm text-white/60">Ingresá con tu usuario para continuar</p>
            @endif
        </div>

        <form method="POST" action="{{ route('login.attempt') }}"
              class="space-y-5 {{ $esHub ? 'md-panel p-7' : 'rounded-2xl bg-white p-6 shadow-2xl' }}">
            @csrf

            <div>
                <label for="usuario" class="mb-1.5 block text-base font-medium {{ $esHub ? 'text-[var(--md-text)]' : 'text-slate-700' }}">Usuario o email</label>
                <input id="usuario" name="usuario" type="text" value="{{ old('usuario') }}" required autofocus
                       autocomplete="username"
                       class="w-full {{ $esHub ? '' : 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200' }}">
            </div>

            <div>
                <label for="password" class="mb-1.5 block text-base font-medium {{ $esHub ? 'text-[var(--md-text)]' : 'text-slate-700' }}">Contraseña</label>
                <input id="password" name="password" type="password" required
                       autocomplete="current-password"
                       class="w-full {{ $esHub ? '' : 'rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200' }}">
            </div>

            @error('usuario')
                <p class="rounded-lg px-3 py-2 text-base {{ $esHub ? 'bg-[#F2D4D1] text-[var(--md-danger)]' : 'bg-red-50 text-red-700' }}">{{ $message }}</p>
            @enderror

            <label class="flex items-center gap-2 text-base {{ $esHub ? 'text-[var(--md-text-mute)]' : 'text-slate-600' }}">
                <input type="checkbox" name="recordarme" value="1">
                Mantener sesión iniciada
            </label>

            <button type="submit"
                    class="w-full {{ $esHub ? 'md-btn md-btn-primary py-3 text-base' : 'rounded-lg bg-indigo-600 py-2.5 text-sm font-semibold text-white hover:bg-indigo-700 focus:ring-2 focus:ring-indigo-300' }}">
                Ingresar
            </button>
        </form>

        <p class="mt-5 text-center text-sm {{ $esHub ? 'text-[var(--md-ink-mute)]' : 'text-slate-500' }}">
            {{ $esHub ? 'Moon Desarrollos · hub de cobros' : 'POS Moon · sistema de gestión de ventas' }}
        </p>
    </div>
</body>
</html>
