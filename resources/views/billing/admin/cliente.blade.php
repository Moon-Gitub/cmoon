@extends('billing.admin.layout')
@section('titulo', $c->nombre)
@php use App\Billing\Models\LegacyDetalle; use App\Billing\Support\Money; $input = 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm'; @endphp

@section('contenido')
    <div class="flex flex-wrap items-start justify-between gap-4">
        <div>
            <h1 class="text-2xl font-semibold">{{ $c->nombre }}</h1>
            <p class="text-sm text-slate-500">{{ $c->dominio ?: 'sin dominio' }} · legacy #{{ $c->legacy_id ?? '—' }} · {{ $c->plataforma }}</p>
            <p class="mt-1 text-sm text-slate-600">
                {{ $c->tipo_documento }} {{ $c->documento ?: $c->cuit ?: 'sin documento' }}
                @if ($c->condicion_iva) · {{ $c->condicion_iva }} @endif
                @if ($c->telefono) · {{ $c->telefono }} @endif
                @if ($c->email) · {{ $c->email }} @endif
            </p>
            @if ($c->direccion)<p class="text-sm text-slate-500">{{ $c->direccion }}</p>@endif
            @if ($c->legacy_bloqueado)<p class="mt-1 text-xs font-medium text-amber-700">En el sistema anterior figuraba bloqueado. Acá solo avisa: no corta el POS.</p>@endif
        </div>
        <div class="text-right">
            <p class="text-xs text-slate-500">Saldo</p>
            <p class="text-3xl font-bold">{{ Money::formato($estado['saldo']) }}</p>
            @include('billing.admin._nivel', ['nivel' => $estado['nivel']])
            @if ($estado['recargo'] > 0)<p class="mt-1 text-xs text-slate-500">Recargo vigente si paga hoy: {{ Money::formato($estado['recargo']) }}</p>@endif
        </div>
    </div>

    @if (session('api_key'))
        <div class="mt-4 rounded-lg border border-amber-300 bg-amber-50 p-4 text-sm">
            <p class="font-semibold">API key del tenant (se muestra una sola vez):</p>
            <code class="mt-1 block break-all font-mono">MOON_BILLING_KEY={{ session('api_key') }}</code>
        </div>
    @endif

    <div class="mt-4 flex flex-wrap gap-2 text-sm">
        <input readonly value="{{ $c->payUrl() }}" class="{{ $input }} max-w-xl font-mono text-xs" onclick="this.select()">
        @if ($whatsapp)
            <a href="{{ $whatsapp }}" target="_blank" rel="noopener" class="rounded-lg bg-emerald-600 px-3 py-2 font-semibold text-white">WhatsApp con link de pago</a>
        @else
            <span class="rounded-lg border border-slate-300 px-3 py-2 text-slate-500">Sin celular para WhatsApp</span>
        @endif
        @if ($whatsappApi && $whatsapp)
            <form method="post" action="{{ route('billing.admin.cliente.whatsapp', $c) }}">@csrf<button class="rounded-lg border border-emerald-700 px-3 py-2 text-emerald-800">Enviar por la API</button></form>
        @endif
        <form method="post" action="{{ route('billing.admin.cliente.link', $c) }}" onsubmit="return confirm('¿Regenerar? El link actual deja de funcionar.')">@csrf<button class="rounded-lg border px-3 py-2">Regenerar link</button></form>
        <form method="post" action="{{ route('billing.admin.cliente.apikey', $c) }}" onsubmit="return confirm('¿Generar nueva API key? La anterior deja de funcionar.')">@csrf<button class="rounded-lg border px-3 py-2">API key tenant</button></form>
    </div>

    <div class="mt-6 grid gap-6 lg:grid-cols-3">
        <div class="space-y-6">
            <form method="post" action="{{ route('billing.admin.cliente.pago', $c) }}" class="rounded-xl bg-white p-4 shadow-sm space-y-2">
                @csrf
                <h2 class="font-semibold">Registrar pago manual</h2>
                <select name="metodo" class="{{ $input }}">
                    <option value="transferencia">Transferencia</option>
                    <option value="efectivo">Efectivo</option>
                    <option value="bonificacion">Bonificación</option>
                </select>
                <input name="importe" placeholder="Importe (ej: 7500)" required class="{{ $input }}">
                <input type="date" name="fecha" value="{{ now()->toDateString() }}" required class="{{ $input }}">
                <input name="referencia" placeholder="Nro de operación (evita duplicados)" class="{{ $input }}">
                <input name="nota" placeholder="Nota" class="{{ $input }}">
                <button class="w-full rounded-lg bg-emerald-600 py-2 text-sm font-semibold text-white">Registrar pago</button>
            </form>

            <form method="post" action="{{ route('billing.admin.cliente.ajuste', $c) }}" class="rounded-xl bg-white p-4 shadow-sm space-y-2">
                @csrf
                <h2 class="font-semibold">Ajuste de saldo</h2>
                <input name="importe" placeholder="+ suma deuda / − resta (ej: -1500)" required class="{{ $input }}">
                <input name="motivo" placeholder="Motivo (obligatorio)" required minlength="5" class="{{ $input }}">
                <button class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white">Registrar ajuste</button>
            </form>

            <form method="post" action="{{ route('billing.admin.cliente.update', $c) }}" class="rounded-xl bg-white p-4 shadow-sm space-y-2">
                @csrf @method('put')
                <h2 class="font-semibold">Datos y política de cobro</h2>
                <input name="nombre" value="{{ $c->nombre }}" required class="{{ $input }}">
                <input name="email" value="{{ $c->email }}" placeholder="Email" class="{{ $input }}">
                <input name="telefono" value="{{ $c->telefono }}" placeholder="WhatsApp" class="{{ $input }}">
                <input name="dominio" value="{{ $c->dominio }}" placeholder="Dominio del POS" class="{{ $input }}">
                <input name="cuit" value="{{ $c->cuit }}" placeholder="CUIT" class="{{ $input }}">
                <label class="block text-xs text-slate-500">Estado
                    <select name="estado" class="{{ $input }}">
                        <option value="activo" @selected($c->estado === 'activo')>Activo</option>
                        <option value="baja" @selected($c->estado === 'baja')>Baja</option>
                    </select></label>
                <label class="block text-xs text-slate-500">Modo de cobro
                    <select name="modo_cobro" class="{{ $input }}">
                        <option value="solo_aviso" @selected($c->modo_cobro === 'solo_aviso')>Solo aviso (nunca bloquea)</option>
                        <option value="bloqueo" @selected($c->modo_cobro === 'bloqueo')>Bloquea por mora</option>
                    </select></label>
                <label class="block text-xs text-slate-500">Prórroga hasta
                    <input type="date" name="prorroga_hasta" value="{{ $c->prorroga_hasta?->toDateString() }}" class="{{ $input }}"></label>
                <label class="flex items-center gap-2 text-sm"><input type="checkbox" name="aplicar_recargos" value="1" @checked($c->aplicar_recargos)> Aplica recargos por mora</label>
                <textarea name="notas" rows="3" placeholder="Notas" class="{{ $input }}">{{ $c->notas }}</textarea>
                <button class="w-full rounded-lg bg-sky-700 py-2 text-sm font-semibold text-white">Guardar</button>
            </form>

            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h2 class="font-semibold">Abonos</h2>
                @foreach ($suscripciones as $s)
                    <form method="post" action="{{ route('billing.admin.cliente.abono', $c) }}" class="mt-3 grid grid-cols-2 gap-2 border-t pt-3">
                        @csrf <input type="hidden" name="subscription_id" value="{{ $s->id }}">
                        <input name="concepto" value="{{ $s->concepto }}" class="{{ $input }} col-span-2">
                        <input name="importe" value="{{ $s->importe_centavos / 100 }}" class="{{ $input }}">
                        <input type="date" name="desde" value="{{ $s->desde->toDateString() }}" class="{{ $input }}">
                        <input type="date" name="hasta" value="{{ $s->hasta?->toDateString() }}" class="{{ $input }}">
                        <button class="rounded-lg border text-sm">Guardar</button>
                    </form>
                @endforeach
                <form method="post" action="{{ route('billing.admin.cliente.abono', $c) }}" class="mt-3 grid grid-cols-2 gap-2 border-t pt-3">
                    @csrf
                    <input name="concepto" placeholder="Nuevo concepto (ej: Sucursal extra)" class="{{ $input }} col-span-2">
                    <input name="importe" placeholder="Importe" class="{{ $input }}">
                    <input type="date" name="desde" value="{{ now()->startOfMonth()->toDateString() }}" class="{{ $input }}">
                    <button class="col-span-2 rounded-lg border py-2 text-sm">Agregar abono</button>
                </form>
            </div>
        </div>

        <div class="space-y-6 lg:col-span-2">
            <div class="rounded-xl bg-white p-4 shadow-sm">
                <h2 class="font-semibold">Cuenta corriente</h2>
                <table class="mt-3 w-full text-sm">
                    <thead class="text-left text-xs uppercase text-slate-500"><tr><th>Fecha</th><th>Concepto</th><th>Medio</th><th>Recibo / MP</th><th class="text-right">Importe</th></tr></thead>
                    @foreach ($movimientos as $m)
                        @php $d = $detalles[LegacyDetalle::idDesdeReferencia($m->referencia)] ?? null; @endphp
                        <tr class="border-t border-slate-100">
                            <td class="py-1.5 whitespace-nowrap">{{ $m->fecha->format('d/m/Y') }}</td>
                            <td>{{ $m->descripcion }}</td>
                            <td class="text-xs text-slate-500">{{ $d->metodo_pago ?? $m->origen }}</td>
                            <td class="text-xs text-slate-500">{{ $d?->numero_recibo }} {{ $d?->referencia_mp }}</td>
                            <td class="text-right font-medium {{ $m->importe_centavos < 0 ? 'text-emerald-700' : '' }}">{{ Money::formato($m->importe_centavos) }}</td>
                        </tr>
                    @endforeach
                </table>
                <div class="mt-3">{{ $movimientos->links() }}</div>
            </div>

            <div class="grid gap-6 md:grid-cols-2">
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Pagos</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @forelse ($pagos as $p)
                            <li class="flex justify-between gap-2"><span>{{ $p->fecha->format('d/m/Y') }} · {{ \Illuminate\Support\Str::limit($p->descripcion, 42) }}</span><b class="text-emerald-700">{{ Money::formato(abs($p->importe_centavos)) }}</b></li>
                        @empty <li class="text-slate-500">Sin pagos.</li> @endforelse
                    </ul>
                </div>
                <div class="rounded-xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">Avisos enviados</h2>
                    <ul class="mt-2 space-y-1 text-sm">
                        @forelse ($avisos as $a)
                            <li>{{ $a->created_at->format('d/m H:i') }} · {{ $a->tipo }} · {{ $a->canal }} · <span class="{{ $a->estado === 'enviado' ? 'text-emerald-700' : 'text-red-600' }}">{{ $a->estado }}</span></li>
                        @empty <li class="text-slate-500">Sin avisos.</li> @endforelse
                    </ul>
                </div>
            </div>
        </div>
    </div>
@endsection
