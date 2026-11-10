<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Cobros') · Moon Cobros</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <style>[x-cloak]{display:none !important}</style>
</head>
<body class="nv min-h-screen bg-[#f5f5f5] text-[#1a1a1a] antialiased">
    <header class="bg-black text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-4 py-3">
            <a href="{{ route('dashboard') }}" class="font-semibold tracking-tight">Moon Cobros</a>
            <nav class="flex gap-1 text-sm">
                @foreach (['dashboard' => 'Tablero', 'clientes' => 'Clientes', 'pagos' => 'Pagos', 'avisos' => 'Avisos', 'eventos' => 'Eventos'] as $r => $t)
                    <a href="{{ route('billing.admin.'.$r) }}"
                       class="rounded px-3 py-1.5 {{ request()->routeIs('billing.admin.'.$r.'*') ? 'bg-[#76b900] font-semibold text-black' : 'text-white/70 hover:text-white' }}">{{ $t }}</a>
                @endforeach
            </nav>
            <span class="ml-auto text-xs text-white/50">{{ auth()->user()?->email }}</span>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-4 py-6">
        @if (session('ok'))
            <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">{{ session('ok') }}</div>
        @endif
        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif
        @if ($errors->any())
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ $errors->first() }}</div>
        @endif

        @yield('contenido')
    </main>
</body>
</html>
