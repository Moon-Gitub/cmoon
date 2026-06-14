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
                <p class="text-sm font-semibold leading-tight text-white">{{ $empresaActual?->nombre_fantasia ?? 'CMoon POS' }}</p>
                <p class="text-xs text-slate-400">{{ $empresaActual?->razon_social ?? 'Sistema de ventas' }}</p>
            </div>
        </div>

        <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
            <a href="{{ route('dashboard') }}"
               class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('dashboard') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6A2.25 2.25 0 016 3.75h2.25A2.25 2.25 0 0110.5 6v2.25a2.25 2.25 0 01-2.25 2.25H6a2.25 2.25 0 01-2.25-2.25V6zM13.5 6a2.25 2.25 0 012.25-2.25H18A2.25 2.25 0 0120.25 6v2.25A2.25 2.25 0 0118 10.5h-2.25a2.25 2.25 0 01-2.25-2.25V6zM3.75 15.75A2.25 2.25 0 016 13.5h2.25a2.25 2.25 0 012.25 2.25V18a2.25 2.25 0 01-2.25 2.25H6A2.25 2.25 0 013.75 18v-2.25zM13.5 15.75a2.25 2.25 0 012.25-2.25H18a2.25 2.25 0 012.25 2.25V18A2.25 2.25 0 0118 20.25h-2.25A2.25 2.25 0 0113.5 18v-2.25z"/></svg>
                Inicio
            </a>

            @canany(['pos.vender', 'ventas.ver', 'cajas.ver'])
                <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Ventas</p>
            @endcanany

            @can('pos.vender')
                <a href="{{ route('pos') }}"
                   class="flex items-center gap-3 rounded-lg bg-emerald-600/20 px-3 py-2 font-semibold text-emerald-300 hover:bg-emerald-600/30">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 3h1.386c.51 0 .955.343 1.087.835l.383 1.437M7.5 14.25a3 3 0 00-3 3h15.75m-12.75-3h11.218c1.121-2.3 2.1-4.684 2.924-7.138a60.114 60.114 0 00-16.536-1.84M7.5 14.25L5.106 5.272M6 20.25a.75.75 0 11-1.5 0 .75.75 0 011.5 0zm12.75 0a.75.75 0 11-1.5 0 .75.75 0 011.5 0z"/></svg>
                    Punto de venta
                </a>
            @endcan

            @can('ventas.ver')
                <a href="{{ route('ventas.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('ventas.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M8.25 6.75h12M8.25 12h12m-12 5.25h12M3.75 6.75h.007v.008H3.75V6.75zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zM3.75 12h.007v.008H3.75V12zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm-.375 5.25h.007v.008H3.75v-.008zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    Ventas
                </a>
            @endcan

            @can('cajas.ver')
                <a href="{{ route('cajas.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('cajas.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/></svg>
                    Cajas
                </a>
            @endcan

            @can('presupuestos.ver')
                <a href="{{ route('presupuestos.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('presupuestos.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m2.25 0H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9zm3.75 11.625a2.625 2.625 0 11-5.25 0 2.625 2.625 0 015.25 0z"/></svg>
                    Presupuestos
                </a>
            @endcan

            @canany(['clientes.ver', 'proveedores.ver', 'compras.ver'])
                <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Comercial</p>
            @endcanany

            @can('compras.ver')
                <a href="{{ route('compras.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('compras.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m8.25 3v6.75m0 0l-3-3m3 3l3-3M3.375 7.5h17.25c.621 0 1.125-.504 1.125-1.125v-1.5c0-.621-.504-1.125-1.125-1.125H3.375c-.621 0-1.125.504-1.125 1.125v1.5c0 .621.504 1.125 1.125 1.125z"/></svg>
                    Compras
                </a>
            @endcan

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
                    Mi empresa
                </a>
            @endcan

            @can('empresas.gestionar')
                <a href="{{ route('empresas.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('empresas.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 21h19.5m-18-18v18m10.5-18v18m6-13.5V21M6.75 6.75h.75m-.75 3h.75m-.75 3h.75m3-6h.75m-.75 3h.75m-.75 3h.75M6.75 21v-3.375c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125V21M3 3h12m-.75 4.5H21m-3.75 3.75h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008zm0 3h.008v.008h-.008v-.008z"/></svg>
                    Empresas
                </a>
            @endcan

            @can('roles.gestionar')
                <a href="{{ route('roles.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('roles.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M15.75 5.25a3 3 0 013 3m3 0a6 6 0 01-7.029 5.912c-.563-.097-1.159.026-1.563.43L10.5 17.25H8.25v2.25H6v2.25H2.25v-2.818c0-.597.237-1.17.659-1.591l6.499-6.499c.404-.404.527-1 .43-1.563A6 6 0 1121.75 8.25z"/></svg>
                    Roles y permisos
                </a>
            @endcan

            @canany(['facturacion.ver', 'emisores.ver', 'informes.ver', 'retenciones.ver'])
                <p class="px-3 pb-1 pt-4 text-xs font-semibold uppercase tracking-wider text-slate-500">Fiscal e informes</p>
            @endcanany

            @can('facturacion.ver')
                <a href="{{ route('facturacion.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('facturacion.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 14.25v-2.625a3.375 3.375 0 00-3.375-3.375h-1.5A1.125 1.125 0 0113.5 7.125v-1.5a3.375 3.375 0 00-3.375-3.375H8.25m0 12.75h7.5m-7.5 3H12M10.5 2.25H5.625c-.621 0-1.125.504-1.125 1.125v17.25c0 .621.504 1.125 1.125 1.125h12.75c.621 0 1.125-.504 1.125-1.125V11.25a9 9 0 00-9-9z"/></svg>
                    Comprobantes
                </a>
            @endcan

            @can('emisores.ver')
                <a href="{{ route('emisores.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('emisores.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 12.75L11.25 15 15 9.75M21 12c0 1.268-.63 2.39-1.593 3.068a3.745 3.745 0 01-1.043 3.296 3.745 3.745 0 01-3.296 1.043A3.745 3.745 0 0112 21c-1.268 0-2.39-.63-3.068-1.593a3.746 3.746 0 01-3.296-1.043 3.745 3.745 0 01-1.043-3.296A3.745 3.745 0 013 12c0-1.268.63-2.39 1.593-3.068a3.745 3.745 0 011.043-3.296 3.746 3.746 0 013.296-1.043A3.746 3.746 0 0112 3c1.268 0 2.39.63 3.068 1.593a3.746 3.746 0 013.296 1.043 3.746 3.746 0 011.043 3.296A3.745 3.745 0 0121 12z"/></svg>
                    Emisores AFIP
                </a>
            @endcan

            @can('informes.ver')
                <a href="{{ route('informes.ventas') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('informes.ventas') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M3 13.125C3 12.504 3.504 12 4.125 12h2.25c.621 0 1.125.504 1.125 1.125v6.75C7.5 20.496 6.996 21 6.375 21h-2.25A1.125 1.125 0 013 19.875v-6.75zM9.75 8.625c0-.621.504-1.125 1.125-1.125h2.25c.621 0 1.125.504 1.125 1.125v11.25c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V8.625zM16.5 4.125c0-.621.504-1.125 1.125-1.125h2.25C20.496 3 21 3.504 21 4.125v15.75c0 .621-.504 1.125-1.125 1.125h-2.25a1.125 1.125 0 01-1.125-1.125V4.125z"/></svg>
                    Informe de ventas
                </a>
                <a href="{{ route('informes.stock') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('informes.stock') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M20.25 7.5l-.625 10.632a2.25 2.25 0 01-2.247 2.118H6.622a2.25 2.25 0 01-2.247-2.118L3.75 7.5m16.5 0H3.75m16.5 0l-1.5-3.75h-13.5L3.75 7.5m6.75 4.5h3"/></svg>
                    Informe de stock
                </a>
                <a href="{{ route('informes.libro-iva') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('informes.libro-iva') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 6.042A8.967 8.967 0 006 3.75c-1.052 0-2.062.18-3 .512v14.25A8.987 8.987 0 016 18c2.305 0 4.408.867 6 2.292m0-14.25a8.966 8.966 0 016-2.292c1.052 0 2.062.18 3 .512v14.25A8.987 8.987 0 0018 18a8.967 8.967 0 00-6 2.292m0-14.25v14.25"/></svg>
                    Libro IVA
                </a>
                <a href="{{ route('informes.cuentas-corrientes') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('informes.cuentas-corrientes') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M2.25 8.25h19.5M2.25 9h19.5m-16.5 5.25h6m-6 2.25h3m-3.75 3h15a2.25 2.25 0 002.25-2.25V6.75A2.25 2.25 0 0019.5 4.5h-15a2.25 2.25 0 00-2.25 2.25v10.5A2.25 2.25 0 004.5 19.5z"/></svg>
                    Cuentas corrientes
                </a>
                <a href="{{ route('informes.cajas') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('informes.cajas') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M21 12a2.25 2.25 0 00-2.25-2.25H15a3 3 0 11-6 0H5.25A2.25 2.25 0 003 12m18 0v6a2.25 2.25 0 01-2.25 2.25H5.25A2.25 2.25 0 013 18v-6m18 0V9M3 12V9m18 0a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 9m18 0V6a2.25 2.25 0 00-2.25-2.25H5.25A2.25 2.25 0 003 6v3"/></svg>
                    Cajas
                </a>
            @endcan

            @can('retenciones.ver')
                <a href="{{ route('retenciones.index') }}"
                   class="flex items-center gap-3 rounded-lg px-3 py-2 {{ request()->routeIs('retenciones.*') ? 'bg-indigo-600 text-white' : 'hover:bg-slate-800' }}">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="1.8" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M9 14.25l6-6m4.5-3.493V21.75l-3.75-1.5-3.75 1.5-3.75-1.5-3.75 1.5V4.757c0-1.108.806-2.057 1.907-2.185a48.507 48.507 0 0111.186 0c1.1.128 1.907 1.077 1.907 2.185zM9.75 9h.008v.008H9.75V9zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0zm4.125 4.5h.008v.008h-.008V13.5zm.375 0a.375.375 0 11-.75 0 .375.375 0 01.75 0z"/></svg>
                    Retenciones IIBB
                </a>
            @endcan
        </nav>

        <div class="border-t border-slate-800 p-3 text-xs text-slate-500">
            CMoon POS · v1.0
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
                <p class="text-sm font-semibold text-white">{{ $empresaActual?->nombre_fantasia ?? 'CMoon POS' }}</p>
                <button type="button" @click="menuAbierto = false" class="rounded-lg p-2 hover:bg-slate-800" aria-label="Cerrar menú">
                    <svg class="h-5 w-5" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M6 18L18 6M6 6l12 12"/></svg>
                </button>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 py-4 text-sm">
                <a href="{{ route('dashboard') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Inicio</a>
                @can('pos.vender')<a href="{{ route('pos') }}" class="block rounded-lg px-3 py-2 text-emerald-300 hover:bg-slate-800">Punto de venta</a>@endcan
                @can('ventas.ver')<a href="{{ route('ventas.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Ventas</a>@endcan
                @can('productos.ver')<a href="{{ route('productos.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Productos</a>@endcan
                @can('clientes.ver')<a href="{{ route('clientes.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Clientes</a>@endcan
                @can('facturacion.ver')<a href="{{ route('facturacion.index') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Comprobantes</a>@endcan
                @can('informes.ver')
                    <a href="{{ route('informes.ventas') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Informe ventas</a>
                    <a href="{{ route('informes.cuentas-corrientes') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Cuentas corrientes</a>
                    <a href="{{ route('informes.cajas') }}" class="block rounded-lg px-3 py-2 hover:bg-slate-800">Informe cajas</a>
                @endcan
            </nav>
        </aside>
    </div>
</div>
<style>[x-cloak]{display:none!important}</style>
</body>
</html>
