@php
    $estilos = [
        'al_dia' => 'border-[var(--md-ok)] text-[var(--md-ok)]',
        'pendiente' => 'border-[var(--md-ink-mute)] text-[var(--md-ink-mute)]',
        'vencido' => 'border-[var(--md-warn)] text-[var(--md-warn)]',
        'por_suspender' => 'border-[var(--md-warn)] text-[var(--md-warn)]',
        'moroso' => 'border-[var(--md-danger)] text-[var(--md-danger)]',
        'suspendido' => 'border-[var(--md-danger)] bg-[var(--md-danger)] text-[var(--md-ink)]',
    ];
@endphp
<span class="inline-block border px-2 py-0.5 text-xs font-medium tracking-wide {{ $estilos[$nivel] ?? 'border-[var(--md-slate-line)]' }}">{{ str_replace('_', ' ', $nivel) }}</span>
