<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @php($empresaActual = auth()->user()->empresa)
    @php($colorAccento = $empresaActual?->color_primario ?? '#4f46e5')
    @php($colorOscuro = \App\Support\Color::oscurecer($colorAccento, 0.15))
    <style>
        /* Personalización visual por empresa: pisa el color de acento de la UI */
        :root { --accent: {{ $colorAccento }}; --accent-dark: {{ $colorOscuro }}; }
        .bg-indigo-600, .bg-indigo-500 { background-color: var(--accent) !important; }
        .hover\:bg-indigo-700:hover { background-color: var(--accent-dark) !important; }
        .text-indigo-600, .text-indigo-700 { color: var(--accent) !important; }
        .hover\:text-indigo-800:hover { color: var(--accent-dark) !important; }
        .border-indigo-500, .focus\:border-indigo-500:focus { border-color: var(--accent) !important; }
        .bg-indigo-100 { background-color: color-mix(in srgb, var(--accent) 12%, white) !important; }
        .bg-indigo-50 { background-color: color-mix(in srgb, var(--accent) 6%, white) !important; }
    </style>
</head>
<body class="bg-slate-100 text-slate-800 antialiased" x-data="{ menuAbierto: false }">
<div class="flex min-h-screen">

    {{-- Sidebar escritorio --}}
    <aside class="hidden w-64 shrink-0 flex-col bg-slate-900 text-slate-200 md:flex">
        <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
            @if ($empresaActual?->logo_path)
                <img src="{{ asset('storage/'.$empresaActual->logo_path) }}" alt="Logo"
                     class="h-9 w-9 rounded-lg bg-white object-contain p-0.5">
            @else
                <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 font-bold text-white">
                    {{ strtoupper(substr($empresaActual?->nombre_fantasia ?? $empresaActual?->razon_social ?? 'C', 0, 1)) }}
                </div>
            @endif
            <div>
                <p class="text-sm font-semibold leading-tight text-white">{{ $empresaActual?->nombre_fantasia ?? 'POSMoon' }}</p>
                <p class="text-xs text-slate-400">{{ $empresaActual?->razon_social ?? 'Sistema de ventas' }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
            @include('layouts.partials.menu-nav')
        </nav>

        <div class="border-t border-slate-800 p-3 text-xs text-slate-500">
            POSMoon · v1.0
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between gap-3 border-b border-slate-200 bg-white px-4 md:px-6">
            <div class="flex min-w-0 items-center gap-3">
                <button type="button" @click="menuAbierto = true"
                        class="rounded-lg p-2 text-slate-600 hover:bg-slate-100 md:hidden" aria-label="Abrir menú">
                    <svg class="h-6 w-6" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5"/></svg>
                </button>
                <h1 class="truncate text-base font-semibold md:text-lg">@yield('titulo', 'Panel')</h1>
            </div>

            <details class="relative">
                <summary class="flex cursor-pointer list-none items-center gap-2 rounded-lg px-2 py-1.5 hover:bg-slate-100">
                    <div class="flex h-8 w-8 items-center justify-center rounded-full bg-indigo-100 text-sm font-semibold text-indigo-700">
                        {{ strtoupper(substr(auth()->user()->name, 0, 1)) }}
                    </div>
                    <div class="hidden text-left sm:block">
                        <p class="text-sm font-medium leading-tight">{{ auth()->user()->name }}</p>
                        <p class="text-xs text-slate-500">{{ auth()->user()->getRoleNames()->first() }}</p>
                    </div>
                    <svg class="h-4 w-4 text-slate-400" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 8.25l-7.5 7.5-7.5-7.5"/></svg>
                </summary>
                <div class="absolute right-0 z-20 mt-2 w-48 rounded-lg border border-slate-200 bg-white py-1 shadow-lg">
                    <a href="{{ route('perfil.edit') }}" class="block px-4 py-2 text-sm text-slate-700 hover:bg-slate-50">
                        Mi perfil
                    </a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button type="submit" class="block w-full px-4 py-2 text-left text-sm text-red-600 hover:bg-slate-50">
                            Cerrar sesión
                        </button>
                    </form>
                </div>
            </details>
        </header>

        <main class="flex-1 overflow-x-hidden p-4 md:p-6">
            @if (session('ok'))
                <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-sm text-emerald-800">
                    {{ session('ok') }}
                </div>
            @endif
            @if (session('error'))
                <div class="mb-4 rounded-lg border border-red-200 bg-red-50 px-4 py-3 text-sm text-red-800">
                    {{ session('error') }}
                </div>
            @endif

            <div class="mx-auto max-w-7xl">
                @yield('contenido')
            </div>
        </main>
    </div>

    {{-- Menú móvil --}}
    <div x-show="menuAbierto" x-cloak class="fixed inset-0 z-40 md:hidden" x-transition.opacity>
        <div class="absolute inset-0 bg-slate-900/50" @click="menuAbierto = false"></div>
        <aside class="absolute left-0 top-0 flex h-full w-72 max-w-[85vw] flex-col bg-slate-900 text-slate-200 shadow-xl">
            <div class="flex h-16 items-center justify-between border-b border-slate-800 px-4">
                <p class="text-sm font-semibold text-white">{{ $empresaActual?->nombre_fantasia ?? 'POSMoon' }}</p>
                <button type="button" @click="menuAbierto = false" class="rounded-lg p-2 hover:bg-slate-800" aria-label="Cerrar menú">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
                @include('layouts.partials.menu-nav')
            </nav>
        </aside>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
@stack('scripts')
</body>
</html>
