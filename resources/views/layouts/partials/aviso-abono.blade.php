@php
    $usuarioAbono = auth()->user();
    $estadoAbono = $usuarioAbono && ($usuarioAbono->esSuperadmin() || $usuarioAbono->hasRole('Administrador'))
        ? app(\App\Services\Moon\MoonBillingClient::class)->estado()
        : null;
@endphp
@if ($estadoAbono && ! in_array($estadoAbono['nivel'], ['al_dia', 'pendiente'], true))
    <div class="mb-4 flex flex-wrap items-center gap-3 rounded-lg border px-4 py-3 text-sm {{ in_array($estadoAbono['nivel'], ['suspendido', 'moroso', 'por_suspender'], true) ? 'border-red-200 bg-red-50 text-red-800' : 'border-amber-200 bg-amber-50 text-amber-800' }}">
        <span class="flex-1">{{ $estadoAbono['mensaje'] }}</span>
        @if ($estadoAbono['pay_url'])
            <a href="{{ $estadoAbono['pay_url'] }}" target="_blank" rel="noopener"
               class="rounded-lg bg-[#009ee3] px-4 py-2 font-semibold text-white hover:bg-[#0089c7]">Pagar ahora</a>
        @endif
    </div>
@endif
