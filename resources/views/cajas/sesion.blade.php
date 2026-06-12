@extends('layouts.app')

@section('titulo', "Sesión de {$sesion->caja->nombre}")

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Resumen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Caja</dt><dd class="font-medium">{{ $sesion->caja->nombre }} ({{ $sesion->caja->sucursal->nombre }})</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Abierta por</dt><dd class="font-medium">{{ $sesion->usuario->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Apertura</dt><dd class="font-medium">{{ $sesion->abierta_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Monto inicial</dt><dd class="font-medium">$ {{ number_format((float) $sesion->monto_apertura, 2, ',', '.') }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2">
                        <dt class="font-semibold">Efectivo esperado</dt>
                        <dd class="font-bold text-indigo-600">$ {{ number_format($efectivoEsperado, 2, ',', '.') }}</dd>
                    </div>
                    @if ($sesion->estado === 'cerrada')
                        <div class="flex justify-between"><dt class="text-slate-500">Contado al cierre</dt><dd class="font-medium">$ {{ number_format((float) $sesion->monto_cierre_declarado, 2, ',', '.') }}</dd></div>
                        @php($diferencia = (float) $sesion->monto_cierre_declarado - (float) $sesion->monto_cierre_sistema)
                        <div class="flex justify-between">
                            <dt class="font-semibold">Diferencia</dt>
                            <dd class="font-bold {{ abs($diferencia) > 0.01 ? 'text-red-600' : 'text-emerald-600' }}">
                                $ {{ number_format($diferencia, 2, ',', '.') }}
                            </dd>
                        </div>
                    @endif
                </dl>
            </div>

            @if ($sesion->estado === 'abierta')
                @can('cajas.operar')
                    <form method="POST" action="{{ route('cajas.movimiento', $sesion) }}"
                          class="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                        @csrf
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Ingreso / egreso de efectivo</h2>
                        <select name="tipo" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                            <option value="ingreso">Ingreso</option>
                            <option value="egreso">Egreso (retiro)</option>
                        </select>
                        <input type="text" name="concepto" placeholder="Concepto" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <input type="number" step="0.01" min="0.01" name="importe" placeholder="Importe $" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <button class="w-full rounded-lg border border-slate-300 py-2 text-sm font-medium hover:bg-slate-50">Registrar</button>
                    </form>

                    <form method="POST" action="{{ route('cajas.cerrar', $sesion) }}"
                          onsubmit="return confirm('¿Cerrar la caja?')"
                          class="space-y-2 rounded-xl border border-amber-200 bg-amber-50 p-5">
                        @csrf
                        <h2 class="text-sm font-semibold text-amber-800">Cerrar caja</h2>
                        <input type="number" step="0.01" min="0" name="monto_cierre_declarado" placeholder="Efectivo contado $" required
                               class="w-full rounded-lg border border-amber-200 px-3 py-2 text-sm">
                        <textarea name="observaciones" rows="2" placeholder="Observaciones (opcional)"
                                  class="w-full rounded-lg border border-amber-200 px-3 py-2 text-sm"></textarea>
                        <button class="w-full rounded-lg bg-amber-600 py-2 text-sm font-semibold text-white hover:bg-amber-700">
                            Cerrar caja
                        </button>
                    </form>
                @endcan
            @endif

            <a href="{{ route('cajas.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a cajas</a>
        </div>

        <div class="space-y-4 lg:col-span-2">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">
                    Ventas de la sesión ({{ $ventas->count() }})
                </h2>
                <div class="max-h-96 space-y-1 overflow-y-auto">
                    @forelse ($ventas as $v)
                        <a href="{{ route('ventas.show', $v) }}"
                           class="flex items-center justify-between rounded-lg px-3 py-2 text-sm hover:bg-slate-50 {{ $v->estado === 'anulada' ? 'opacity-50' : '' }}">
                            <span class="font-mono">#{{ str_pad($v->numero, 6, '0', STR_PAD_LEFT) }}</span>
                            <span class="text-xs text-slate-500">{{ $v->fecha->format('H:i') }}</span>
                            <span class="text-xs text-slate-500">{{ $v->pagos->map(fn ($p) => $p->medioPago->nombre)->implode(', ') }}</span>
                            <span class="font-semibold">$ {{ number_format((float) $v->total, 2, ',', '.') }}</span>
                        </a>
                    @empty
                        <p class="py-6 text-center text-sm text-slate-400">Sin ventas en esta sesión.</p>
                    @endforelse
                </div>
            </div>

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Movimientos de efectivo</h2>
                <div class="space-y-1">
                    @forelse ($sesion->movimientos as $mov)
                        <div class="flex items-center justify-between rounded-lg px-3 py-2 text-sm">
                            <span>{{ $mov->concepto }}</span>
                            <span class="text-xs text-slate-500">{{ $mov->created_at->format('H:i') }} · {{ $mov->usuario?->name }}</span>
                            <span class="font-semibold {{ $mov->tipo === 'ingreso' ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ $mov->tipo === 'ingreso' ? '+' : '−' }} $ {{ number_format((float) $mov->importe, 2, ',', '.') }}
                            </span>
                        </div>
                    @empty
                        <p class="py-4 text-center text-sm text-slate-400">Sin movimientos manuales.</p>
                    @endforelse
                </div>
            </div>
        </div>
    </div>
@endsection
