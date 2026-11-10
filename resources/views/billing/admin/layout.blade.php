<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Cobros') · Moon Cobros</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-slate-100 font-sans text-slate-900 antialiased">
    <header class="bg-slate-900 text-white">
        <div class="mx-auto flex max-w-7xl flex-wrap items-center gap-6 px-4 py-3">
            <span class="font-semibold">🌙 Moon Cobros</span>
            <nav class="flex gap-4 text-sm">
                @foreach (['dashboard' => 'Tablero', 'clientes' => 'Clientes', 'pagos' => 'Pagos', 'avisos' => 'Avisos', 'eventos' => 'Eventos'] as $r => $t)
                    <a href="{{ route('billing.admin.'.$r) }}" class="{{ request()->routeIs('billing.admin.'.$r.'*') ? 'text-white' : 'text-slate-400 hover:text-white' }}">{{ $t }}</a>
                @endforeach
            </nav>
            <span class="ml-auto text-xs text-slate-400">{{ auth()->user()?->email }}</span>
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
