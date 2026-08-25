@props([
    'column',
    'label',
    'sort' => 'id',
    'dir' => 'asc',
    'defaultDir' => 'asc',
    'align' => 'left',
])

@php
    $activa = $sort === $column;
    $siguiente = ($activa && $dir === 'asc') ? 'desc' : 'asc';
    $href = request()->fullUrlWithQuery([
        'sort' => $column,
        'dir' => $activa ? $siguiente : $defaultDir,
        'page' => null,
    ]);
    $base = $align === 'right' ? 'px-4 py-3 text-right' : 'px-4 py-3';
@endphp

<th {{ $attributes->class([$base]) }}>
    <a href="{{ $href }}"
       class="inline-flex items-center gap-1 hover:text-slate-800 {{ $activa ? 'text-slate-800' : '' }} {{ $align === 'right' ? 'justify-end' : '' }}"
       title="Ordenar por {{ strtolower($label) }}">
        <span>{{ $label }}</span>
        @if ($activa)
            <span aria-hidden="true">{{ $dir === 'asc' ? '↑' : '↓' }}</span>
        @else
            <span class="text-slate-300" aria-hidden="true">↕</span>
        @endif
    </a>
</th>
