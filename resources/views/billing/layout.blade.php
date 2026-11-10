<!doctype html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex, nofollow">
    <title>@yield('titulo', 'Pagar abono') · POSMoon</title>
    @vite(['resources/css/app.css'])
</head>
<body class="nv min-h-screen bg-[#f5f5f5] text-[#1a1a1a] antialiased">
    <div class="mx-auto max-w-lg px-4 py-6 sm:py-10">
        <div class="mb-6 text-lg font-semibold tracking-tight">POSMoon</div>

        @if (session('error'))
            <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">{{ session('error') }}</div>
        @endif

        @yield('contenido')

        <p class="mt-8 text-center text-xs text-slate-500">Moon Desarrollos · Pagos procesados por Mercado Pago</p>
    </div>
    @stack('scripts')
</body>
</html>
