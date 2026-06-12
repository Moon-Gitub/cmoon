<!DOCTYPE html>
<html lang="es">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('titulo', 'Panel') · {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-100 text-slate-800 antialiased">
<div class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside class="hidden w-64 shrink-0 flex-col bg-slate-900 text-slate-200 md:flex">
        <div class="flex h-16 items-center gap-2 border-b border-slate-800 px-5">
            <div class="flex h-9 w-9 items-center justify-center rounded-lg bg-indigo-500 font-bold text-white">C</div>
            <div>
                <p class="text-sm font-semibold leading-tight text-white">CMoon POS</p>
                <p class="text-xs text-slate-400">{{ auth()->user()->empresa?->nombre_fantasia ?? 'Sistema de ventas' }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                Inicio
            </a>

            @canany(['clientes.ver', 'proveedores.ver'])
                <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Comercial</p>
            @endcanany

            @can('clientes.ver')
                <a href="{{ route('clientes.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('clientes.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M17.982 18.725A7.488 7.488 0 0012 15.75a7.488 7.488 0 00-5.982 2.975m11.963 0a9 9 0 10-11.963 0m11.963 0A8.966 8.966 0 0112 21a8.966 8.966 0 01-5.982-2.275M15 9.75a3 3 0 11-6 0 3 3 0 016 0z"/></svg>
                    Clientes
                </a>
            @endcan

            @can('proveedores.ver')
                <a href="{{ route('proveedores.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('proveedores.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 18.75a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h6m-9 0H3.375a1.125 1.125 0 01-1.125-1.125V14.25m17.25 4.5a1.5 1.5 0 01-3 0m3 0a1.5 1.5 0 00-3 0m3 0h1.125c.621 0 1.129-.504 1.09-1.124a17.902 17.902 0 00-3.213-9.193 2.056 2.056 0 00-1.58-.86H14.25M16.5 18.75h-2.25m0-11.177v-.958c0-.568-.422-1.048-.987-1.106a48.554 48.554 0 00-10.026 0 1.106 1.106 0 00-.987 1.106v7.635m12-6.677v6.677m0 4.5v-4.5m0 0h-12"/></svg>
                    Proveedores
                </a>
            @endcan

            @canany(['productos.ver', 'categorias.ver', 'listas-precio.ver'])
                <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Catálogo</p>
            @endcanany

            @can('productos.ver')
                <a href="{{ route('productos.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('productos.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5M10 11.25h4M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                    Productos
                </a>
            @endcan

            @can('categorias.ver')
                <a href="{{ route('categorias.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('categorias.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9.568 3H5.25A2.25 2.25 0 003 5.25v4.318c0 .597.237 1.17.659 1.591l9.581 9.581c.699.699 1.78.872 2.607.33a18.095 18.095 0 005.223-5.223c.542-.827.369-1.908-.33-2.607L11.16 3.66A2.25 2.25 0 009.568 3z"/><path stroke-linecap="round" stroke-linejoin="round" d="M6 6h.008v.008H6V6z"/></svg>
                    Categorías
                </a>
            @endcan

            @can('listas-precio.ver')
                <a href="{{ route('listas-precio.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('listas-precio.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    Listas de precio
                </a>
            @endcan

            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Administración</p>

            @can('usuarios.ver')
                <a href="{{ route('usuarios.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('usuarios.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15 19.128a9.38 9.38 0 002.625.372 9.337 9.337 0 004.121-.952 4.125 4.125 0 00-7.533-2.493M15 19.128v-.003c0-1.113-.285-2.16-.786-3.07M15 19.128v.106A12.318 12.318 0 018.624 21c-2.331 0-4.512-.645-6.374-1.766l-.001-.109a6.375 6.375 0 0111.964-3.07M12 6.375a3.375 3.375 0 11-6.75 0 3.375 3.375 0 016.75 0zm8.25 2.25a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    Usuarios
                </a>
            @endcan

            @can('sucursales.ver')
                <a href="{{ route('sucursales.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('sucursales.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M13.5 21v-7.5a.75.75 0 01.75-.75h3a.75.75 0 01.75.75V21m-4.5 0H2.36m11.14 0H18m0 0h3.64m-1.39 0V9.349m-16.5 11.65V9.35m0 0a3.001 3.001 0 003.75-.615A2.993 2.993 0 009.75 9.75c.896 0 1.7-.393 2.25-1.016a2.993 2.993 0 002.25 1.016c.896 0 1.7-.393 2.25-1.016a3.001 3.001 0 003.75.614m-16.5 0a3.004 3.004 0 01-.621-4.72L4.318 3.44A1.5 1.5 0 015.378 3h13.243a1.5 1.5 0 011.06.44l1.19 1.189a3 3 0 01-.621 4.72m-13.5 8.65h3.75a.75.75 0 00.75-.75V13.5a.75.75 0 00-.75-.75H6.75a.75.75 0 00-.75.75v3.75c0 .415.336.75.75.75z"/></svg>
                    Sucursales
                </a>
            @endcan

            @can('medios-pago.ver')
                <a href="{{ route('medios-pago.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('medios-pago.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                    Medios de pago
                </a>
            @endcan

            @can('empresa.ver')
                <a href="{{ route('empresa.edit') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('empresa.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 21h16.5M4.5 3h15M5.25 3v18m13.5-18v18M9 6.75h1.5m-1.5 3h1.5m-1.5 3h1.5m3-6H15m-1.5 3H15m-1.5 3H15M9 21v-3.375c0-.621.504-1.125 1.125-1.125h3.75c.621 0 1.125.504 1.125 1.125V21"/></svg>
                    Empresa
                </a>
            @endcan

            <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Próximas fases</p>
            @foreach (['Ventas', 'Caja', 'Compras', 'Informes'] as $modulo)
                <span class="flex cursor-not-allowed items-center gap-3 rounded-lg px-3 py-2 text-slate-500">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M16.5 10.5V6.75a4.5 4.5 0 10-9 0v3.75m-.75 11.25h10.5a2.25 2.25 0 002.25-2.25v-6.75a2.25 2.25 0 00-2.25-2.25H6.75a2.25 2.25 0 00-2.25 2.25v6.75a2.25 2.25 0 002.25 2.25z"/></svg>
                    {{ $modulo }}
                </span>
            @endforeach
        </nav>

        <div class="border-t border-slate-800 p-3 text-xs text-slate-500">
            v0.1 · Fase 1
        </div>
    </aside>

    {{-- Contenido --}}
    <div class="flex min-w-0 flex-1 flex-col">
        <header class="flex h-16 items-center justify-between border-b border-slate-200 bg-white px-4 md:px-6">
            <h1 class="text-lg font-semibold">@yield('titulo', 'Panel')</h1>

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

        <main class="flex-1 p-4 md:p-6">
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

            @yield('contenido')
        </main>
    </div>
</div>
</body>
</html>
