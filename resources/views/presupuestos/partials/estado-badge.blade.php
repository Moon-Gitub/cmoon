@php
    $estadosPresupuesto = [
        'pendiente_aprobacion' => ['label' => 'Pendiente aprobación', 'class' => 'bg-orange-50 text-orange-700'],
        'aprobado' => ['label' => 'Aprobado', 'class' => 'bg-sky-50 text-sky-700'],
        'pendiente' => ['label' => 'Pendiente', 'class' => 'bg-amber-50 text-amber-700'],
        'convertido' => ['label' => 'Convertido', 'class' => 'bg-emerald-50 text-emerald-700'],
        'rechazado' => ['label' => 'Rechazado', 'class' => 'bg-red-50 text-red-600'],
        'anulado' => ['label' => 'Anulado', 'class' => 'bg-red-50 text-red-600'],
    ];
    $badge = $estadosPresupuesto[$presupuesto->estado] ?? $estadosPresupuesto['anulado'];
@endphp
<span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $badge['class'] }}">{{ $badge['label'] }}</span>
@if ($presupuesto->origen === 'movil')
    <span class="ml-1 rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-600">Móvil</span>
@endif
