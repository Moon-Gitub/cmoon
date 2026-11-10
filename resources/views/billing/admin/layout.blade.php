<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Cobros') · Moon Cobros</title>
    <link rel="icon" href="{{ asset('brand/moon-mark.svg') }}" type="image/svg+xml">
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="md md-hub min-h-screen antialiased">
    <header class="md-top sticky top-0 z-30">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-4 px-6 py-3.5">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-display">
                <img src="{{ asset('brand/moon-mark-on-dark.svg') }}" alt="" class="h-9 w-9" width="36" height="36">
                <span class="text-[15px] tracking-[0.18em] text-white">MOON <span class="font-medium opacity-90">COBROS</span></span>
            </a>
            <nav class="flex flex-wrap items-center gap-0.5 text-[15px]">
                @foreach (['dashboard' => 'Tablero', 'clientes' => 'Clientes', 'pagos' => 'Pagos', 'avisos' => 'Avisos', 'eventos' => 'Eventos'] as $r => $t)
                    <a href="{{ route('billing.admin.'.$r) }}"
                       class="rounded-md px-3.5 py-2 {{ request()->routeIs('billing.admin.'.$r.'*') ? 'bg-white/15 font-semibold text-white' : 'text-[var(--md-ink-mute)] hover:bg-white/10 hover:text-white' }}">{{ $t }}</a>
                @endforeach
            </nav>
            <span class="ml-auto text-sm text-[var(--md-ink-mute)]">{{ auth()->user()?->email }}</span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-8">
        @if (session('ok'))
            <div class="md-panel mb-5 border-l-4 border-l-[var(--md-ok)] px-5 py-4 text-[var(--md-ok)]">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="md-panel mb-5 border-l-4 border-l-[var(--md-danger)] px-5 py-4 text-[var(--md-danger)]">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="md-panel mb-5 border-l-4 border-l-[var(--md-danger)] px-5 py-4 text-[var(--md-danger)]">{{ $errors->first() }}</div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
