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
<body class="md min-h-screen antialiased">
    <header class="md-top sticky top-0 z-30 border-b">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-5 px-5 py-4">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-display">
                <img src="{{ asset('brand/moon-mark.svg') }}" alt="" class="h-10 w-10" width="40" height="40">
                <span class="text-base tracking-[0.2em] text-[var(--md-ink)]">MOON <span class="font-medium text-white">COBROS</span></span>
            </a>
            <nav class="flex flex-wrap gap-1 text-base">
                @foreach (['dashboard' => 'Tablero', 'clientes' => 'Clientes', 'pagos' => 'Pagos', 'avisos' => 'Avisos', 'eventos' => 'Eventos'] as $r => $t)
                    <a href="{{ route('billing.admin.'.$r) }}"
                       class="rounded-lg px-3.5 py-2 {{ request()->routeIs('billing.admin.'.$r.'*') ? 'bg-white/15 font-semibold text-white' : 'text-[var(--md-ink-mute)] hover:bg-white/10 hover:text-white' }}">{{ $t }}</a>
                @endforeach
            </nav>
            <span class="ml-auto text-sm text-[var(--md-ink-mute)]">{{ auth()->user()?->email }}</span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-5 py-8">
        @if (session('ok'))
            <div class="md-panel mb-5 px-5 py-4 text-[var(--md-ok)]">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="md-panel mb-5 px-5 py-4 text-[var(--md-danger)]">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="md-panel mb-5 px-5 py-4 text-[var(--md-danger)]">{{ $errors->first() }}</div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
