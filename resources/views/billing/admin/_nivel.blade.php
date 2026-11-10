@php
    $estilos = [
        'al_dia' => 'md-badge md-badge-ok',
        'pendiente' => 'md-badge md-badge-mute',
        'vencido' => 'md-badge md-badge-warn',
        'por_suspender' => 'md-badge md-badge-warn',
        'moroso' => 'md-badge md-badge-danger',
        'suspendido' => 'md-badge md-badge-solid',
    ];
@endphp
<span class="{{ $estilos[$nivel] ?? 'md-badge md-badge-mute' }}">{{ str_replace('_', ' ', $nivel) }}</span>
