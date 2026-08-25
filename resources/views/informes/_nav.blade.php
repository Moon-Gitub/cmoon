{{-- Navegación interna del módulo Informes --}}
@php
    $links = [
        ['route' => 'informes.index', 'label' => 'Inicio', 'match' => 'informes.index'],
        ['route' => 'informes.ventas', 'label' => 'Ventas', 'match' => 'informes.ventas'],
        ['route' => 'informes.productos', 'label' => 'Productos', 'match' => 'informes.productos'],
        ['route' => 'informes.rentabilidad', 'label' => 'Rentabilidad', 'match' => 'informes.rentabilidad'],
        ['route' => 'informes.categorias', 'label' => 'Categorías', 'match' => 'informes.categorias'],
        ['route' => 'informes.pedidos', 'label' => '¿Qué pedir?', 'match' => 'informes.pedidos'],
        ['route' => 'informes.stock', 'label' => 'Stock', 'match' => 'informes.stock'],
        ['route' => 'informes.libro-iva', 'label' => 'Libro IVA', 'match' => 'informes.libro-iva'],
        ['route' => 'informes.cuentas-corrientes', 'label' => 'Cta. cte.', 'match' => 'informes.cuentas-corrientes'],
        ['route' => 'informes.cajas', 'label' => 'Cajas', 'match' => 'informes.cajas'],
    ];
@endphp
<nav class="mb-4 flex flex-wrap gap-1.5 border-b border-slate-200 pb-3">
    @foreach ($links as $link)
        <a href="{{ route($link['route']) }}"
           class="rounded-lg px-3 py-1.5 text-xs font-semibold {{ request()->routeIs($link['match']) ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-600 hover:bg-slate-200' }}">
            {{ $link['label'] }}
        </a>
    @endforeach
</nav>
