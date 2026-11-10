<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Pagar abono') · Moon Cobros</title>
    <link rel="icon" href="{{ asset('brand/moon-mark.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css'])
</head>
<body class="md min-h-screen antialiased">
    <div class="mx-auto max-w-lg px-4 py-6 sm:py-10">
        <div class="mb-8 flex items-center gap-3 font-display tracking-[0.18em] text-[var(--md-slate)]">
            <img src="{{ asset('brand/moon-mark-on-light.svg') }}" alt="" class="h-9 w-9" width="36" height="36">
            <span class="text-sm font-light">MOON <span class="font-medium text-[var(--md-text-mute)]">COBROS</span></span>
        </div>

        @if (session('error'))
            <div class="mb-4 border border-[var(--md-danger)] px-4 py-3 text-sm">{{ session('error') }}</div>
        @endif

        @yield('contenido')

        <p class="mt-8 text-center text-xs text-[var(--md-ink-mute)]">Moon Desarrollos · Pagos procesados por Mercado Pago</p>
    </div>
    @stack('scripts')
</body>
</html>
