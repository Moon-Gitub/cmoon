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
    <div class="flex flex-wrap items-end justify-between gap-3">
        <div>
            <h1 class="text-2xl font-semibold">Avisos de hoy</h1>
            <p class="mt-1 max-w-3xl text-sm text-slate-600">
                Día {{ $cola['dia'] }}. El automático avisa el 1 (cuota), el 8 (recordatorio), el 15 (vencido) y el 22 (por suspender, solo si el cliente está en modo bloqueo).
                @if ($cola['programado'])
                    Hoy correspondería <b>{{ $nombres[$cola['programado']] }}</b>.
                @else
                    Hoy no toca un aviso automático. Igual están los que deben, con el texto según su estado, para mandarlo a mano.
                @endif
                Esta pantalla no envía nada sola.
                @unless ($automaticoActivo) El envío automático está apagado. @endunless
            </p>
        </div>
        <p class="text-sm text-slate-500">{{ count($cola['filas']) }} con saldo</p>
    </div>

    <div class="mt-4 overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-4 py-2">Cliente</th>
                    <th>Teléfono</th>
                    <th>Aviso</th>
                    <th class="text-right">Saldo</th>
                    <th class="px-4"></th>
                </tr>
            </thead>
            @forelse ($cola['filas'] as $f)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2">
                        <a class="text-sky-800 hover:underline" href="{{ route('billing.admin.cliente', $f['customer']) }}">{{ $f['customer']->nombre }}</a>
                        <span class="ml-1 text-xs text-slate-400">{{ $f['nivel'] }}</span>
                    </td>
                    <td>{{ $f['customer']->telefono ?: '—' }}</td>
                    <td>
                        {{ $nombres[$f['tipo']] ?? $f['tipo'] }}
                        @if ($cola['programado'] && ! $f['automatico'])
                            <span class="block text-xs text-amber-700">el automático de hoy no lo incluye</span>
                        @endif
                    </td>
                    <td class="text-right font-medium">{{ Money::formato($f['saldo']) }}</td>
                    <td class="px-4 text-right whitespace-nowrap">
                        @if ($f['whatsapp'])
                            <a href="{{ $f['whatsapp'] }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-600 px-3 py-1.5 text-xs font-semibold text-white">WhatsApp</a>
                        @else
                            <span class="text-xs text-slate-400">sin celular</span>
                        @endif
                        <form method="POST" action="{{ route('billing.admin.avisos.marcar', $f['customer']) }}" class="inline">
                            @csrf
                            <button class="ml-1 rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-semibold text-slate-700">Marcar avisado</button>
                        </form>
                    </td>
                </tr>
            @empty
                <tr><td class="px-4 py-6 text-slate-500" colspan="5">Nadie pendiente. {{ count($cola['avisados']) ? 'Los de hoy ya están marcados.' : 'Nadie supera la tolerancia.' }}</td></tr>
            @endforelse
        </table>
    </div>

    @if (count($cola['avisados']))
        <h2 class="mt-6 text-sm font-semibold text-slate-500">Ya avisados hoy ({{ count($cola['avisados']) }})</h2>
        <ul class="mt-2 space-y-1 text-sm text-slate-600">
            @foreach ($cola['avisados'] as $f)
                <li>{{ $f['customer']->nombre }} · {{ Money::formato($f['saldo']) }}</li>
            @endforeach
        </ul>
    @endif
@endsection
