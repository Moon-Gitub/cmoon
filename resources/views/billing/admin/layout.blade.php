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
    <header class="border-b border-[var(--md-slate-line)] bg-[var(--md-slate-deep)]">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="flex items-center gap-3 font-display tracking-[0.18em]">
                <img src="{{ asset('brand/moon-mark.svg') }}" alt="" class="h-8 w-8" width="32" height="32">
                <span class="text-sm font-light text-[var(--md-ink)]">MOON <span class="font-normal text-[var(--md-ink-mute)]">COBROS</span></span>
            </a>
            <nav class="flex gap-1 text-sm font-display tracking-wide">
                @foreach (['dashboard' => 'Tablero', 'clientes' => 'Clientes', 'pagos' => 'Pagos', 'avisos' => 'Avisos', 'eventos' => 'Eventos'] as $r => $t)
                    <a href="{{ route('billing.admin.'.$r) }}"
                       class="px-3 py-1.5 {{ request()->routeIs('billing.admin.'.$r.'*') ? 'border-b-2 border-[var(--md-ink)] font-medium text-[var(--md-ink)]' : 'text-[var(--md-ink-mute)] hover:text-[var(--md-ink)]' }}">{{ $t }}</a>
                @endforeach
            </nav>
            <span class="ml-auto text-xs text-[var(--md-ink-mute)]">{{ auth()->user()?->email }}</span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('ok'))
            <div class="mb-4 border border-[var(--md-ok)] px-4 py-3 text-sm text-[var(--md-ink)]">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 border border-[var(--md-danger)] px-4 py-3 text-sm text-[var(--md-ink)]">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 border border-[var(--md-danger)] px-4 py-3 text-sm text-[var(--md-ink)]">{{ $errors->first() }}</div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
