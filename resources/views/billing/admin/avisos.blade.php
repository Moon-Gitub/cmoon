@extends('billing.admin.layout')
@section('titulo', 'Avisos')
@php
    use App\Billing\Support\Money;
    $nombres = [
        'cuota_emitida' => 'cuota emitida',
        'recordatorio' => 'recordatorio',
        'vencido' => 'vencido',
        'por_suspender' => 'por suspender',
    ];
@endphp

@section('contenido')
    <div class="mb-6 flex flex-wrap items-end justify-between gap-4">
        <div class="max-w-3xl">
            <h1 class="font-display text-3xl font-medium tracking-tight text-white">Avisos de hoy</h1>
            <p class="mt-2 text-base text-[var(--md-ink-mute)]">
                Día {{ $cola['dia'] }}. El automático avisa el 1 (cuota), el 8 (recordatorio), el 15 (vencido) y el 22 (por suspender, solo si el cliente está en modo bloqueo).
                @if ($cola['programado'])
                    Hoy correspondería <b class="text-white">{{ $nombres[$cola['programado']] }}</b>.
                @else
                    Hoy no toca un aviso automático. Igual aparecen los que deben, para mandarlo a mano.
                @endif
                Esta pantalla no envía nada sola.
                @unless ($automaticoActivo) El envío automático está apagado. @endunless
            </p>
        </div>
        <p class="text-base text-[var(--md-ink-mute)]">{{ count($cola['filas']) }} con saldo</p>
    </div>

    <div class="md-panel overflow-x-auto">
        <table class="w-full text-left">
            <thead>
                <tr>
                    <th>Cliente</th>
                    <th>Teléfono</th>
                    <th>Aviso</th>
                    <th class="text-right">Saldo</th>
                    <th class="text-right">Acciones</th>
                </tr>
            </thead>
            <tbody>
            @forelse ($cola['filas'] as $f)
                <tr>
                    <td>
                        <a href="{{ route('billing.admin.cliente', $f['customer']) }}">{{ $f['customer']->nombre }}</a>
                        <span class="ml-2 text-[var(--md-text-mute)]">{{ $f['nivel'] }}</span>
                    </td>
                    <td>{{ $f['customer']->telefono ?: '—' }}</td>
                    <td>
                        {{ $nombres[$f['tipo']] ?? $f['tipo'] }}
                        @if ($cola['programado'] && ! $f['automatico'])
                            <span class="mt-1 block text-[var(--md-warn)]">el automático de hoy no lo incluye</span>
                        @endif
                    </td>
                    <td class="md-money text-right text-lg">{{ Money::formato($f['saldo']) }}</td>
                    <td class="space-x-2 text-right whitespace-nowrap">
                        @if ($f['whatsapp'])
                            <a href="{{ $f['whatsapp'] }}" target="_blank" rel="noopener" class="md-btn md-btn-wa">WhatsApp</a>
                        @else
                            <span class="text-[var(--md-text-mute)]">sin celular</span>
                        @endif
                        <form method="POST" action="{{ route('billing.admin.avisos.marcar', $f['customer']) }}" class="inline">
                            @csrf
                            <button class="md-btn md-btn-ghost border border-[var(--md-edge)]">Marcar avisado</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="py-8 text-[var(--md-text-mute)]" colspan="5">Nadie pendiente. {{ count($cola['avisados']) ? 'Los de hoy ya están marcados.' : 'Nadie supera la tolerancia.' }}</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>

    @if (count($cola['avisados']))
        <h2 class="mt-8 text-lg font-semibold text-white">Ya avisados hoy ({{ count($cola['avisados']) }})</h2>
        <ul class="mt-3 space-y-2 text-base text-[var(--md-ink-mute)]">
            @foreach ($cola['avisados'] as $f)
                <li>{{ $f['customer']->nombre }} · {{ Money::formato($f['saldo']) }}</li>
            @endforeach
        </ul>
    @endif
@endsection
