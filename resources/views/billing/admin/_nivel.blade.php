@php
    $estilos = [
        'al_dia' => 'bg-emerald-100 text-emerald-800',
        'pendiente' => 'bg-sky-100 text-sky-800',
        'vencido' => 'bg-amber-100 text-amber-800',
        'por_suspender' => 'bg-orange-100 text-orange-800',
        'moroso' => 'bg-red-100 text-red-700',
        'suspendido' => 'bg-red-600 text-white',
    ];
@endphp
<span class="inline-block rounded-full px-2 py-0.5 text-xs font-medium {{ $estilos[$nivel] ?? 'bg-slate-100' }}">{{ str_replace('_', ' ', $nivel) }}</span>
