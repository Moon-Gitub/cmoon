@extends('layouts.app')

@section('titulo', "Sesión de {$sesion->caja->nombre}")

@section('contenido')
    @php
        $hintMedio = function (string $tipo, string $nombre): string {
            return match ($tipo) {
                'efectivo' => 'Plata física en el cajón (billetes y monedas).',
                'cuenta_corriente' => 'Total fiado / cuenta corriente del turno. No es plata en mano.',
                default => "Total cobrado en {$nombre} (lo que figura en la app o el banco).",
            };
        };
    @endphp

    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3" x-data="cierreCiego()">

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Resumen</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Caja</dt><dd class="font-medium">{{ $sesion->caja->nombre }} ({{ $sesion->caja->sucursal->nombre }})</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Abierta por</dt><dd class="font-medium">{{ $sesion->usuario->name }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Apertura</dt><dd class="font-medium">{{ $sesion->abierta_at->format('d/m/Y H:i') }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Monto inicial</dt><dd class="font-medium">$ {{ number_format((float) $sesion->monto_apertura, 2, ',', '.') }}</dd></div>
                    @if ($sesion->estado === 'cerrada')
                        <div class="flex justify-between"><dt class="text-slate-500">Cerrada</dt><dd class="font-medium">{{ $sesion->cerrada_at?->format('d/m/Y H:i') }}</dd></div>
                        <div class="flex justify-between"><dt class="text-slate-500">Cambio próximo turno</dt><dd class="font-medium">$ {{ number_format((float) ($sesion->apertura_siguiente_monto ?? 0), 2, ',', '.') }}</dd></div>
                        @php($difEf = (float) $sesion->monto_cierre_sistema - (float) $sesion->monto_cierre_declarado)
                        <div class="flex justify-between border-t border-slate-100 pt-2">
                            <dt class="font-semibold">Dif. efectivo</dt>
                            <dd class="font-bold {{ abs($difEf) > 0.01 ? 'text-red-600' : 'text-emerald-600' }}">
                                $ {{ number_format($difEf, 2, ',', '.') }}
                                <span class="text-xs font-normal text-slate-500">({{ $difEf > 0.01 ? 'faltante' : ($difEf < -0.01 ? 'sobrante' : 'OK') }})</span>
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
                @endcan
            @endif

            <a href="{{ route('cajas.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a cajas</a>
        </div>

        <div class="space-y-4 lg:col-span-2">
            @if ($sesion->estado === 'abierta')
                @can('cajas.operar')
                    <div class="overflow-hidden rounded-2xl border border-slate-200 bg-white shadow-sm">
                        <div class="border-b border-slate-100 bg-slate-50 px-5 py-4">
                            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Cómo cerrar la caja</p>
                            <h2 class="mt-1 text-lg font-semibold text-slate-900">Cierre ciego: vos contás, el sistema compara después</h2>
                        </div>

                        <ol class="grid grid-cols-1 gap-0 divide-y divide-slate-100 sm:grid-cols-4 sm:divide-x sm:divide-y-0">
                            <li class="relative px-5 py-4">
                                <span class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">1</span>
                                <p class="text-sm font-semibold text-slate-800">Contá el cajón</p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">Billetes y monedas que hay ahora. Podés usar el contador.</p>
                                <span class="flecha-cierre pointer-events-none absolute right-2 top-8 hidden text-2xl text-slate-400 sm:block" aria-hidden="true">→</span>
                            </li>
                            <li class="relative px-5 py-4">
                                <span class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">2</span>
                                <p class="text-sm font-semibold text-slate-800">Anotá cada medio</p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">Efectivo = plata. MP / transferencia = lo cobrado. Cuenta corriente = fiado.</p>
                                <span class="flecha-cierre pointer-events-none absolute right-2 top-8 hidden text-2xl text-slate-400 sm:block" aria-hidden="true">→</span>
                            </li>
                            <li class="relative px-5 py-4">
                                <span class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">3</span>
                                <p class="text-sm font-semibold text-slate-800">Qué queda mañana</p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">El efectivo que dejás en caja para el próximo turno.</p>
                                <span class="flecha-cierre pointer-events-none absolute right-2 top-8 hidden text-2xl text-slate-400 sm:block" aria-hidden="true">→</span>
                            </li>
                            <li class="px-5 py-4">
                                <span class="mb-2 flex h-8 w-8 items-center justify-center rounded-full bg-slate-900 text-sm font-bold text-white">4</span>
                                <p class="text-sm font-semibold text-slate-800">Guardá</p>
                                <p class="mt-1 text-xs leading-relaxed text-slate-500">Ahí recién se ve el esperado y si sobró o faltó.</p>
                            </li>
                        </ol>

                        <div class="flex items-center gap-3 border-t border-slate-100 bg-slate-50 px-5 py-3">
                            <span class="esperado-oculto inline-block rounded-md bg-slate-200 px-3 py-1 font-mono text-sm text-slate-400">$ •••••</span>
                            <span class="flecha-cierre text-lg text-slate-400" aria-hidden="true">→</span>
                            <p class="text-xs text-slate-600">
                                El <strong>esperado del sistema no se muestra ahora</strong>. Aparece al guardar, al lado de lo que cargaste.
                            </p>
                        </div>
                    </div>

                    <form method="POST" action="{{ route('cajas.cerrar', $sesion) }}"
                          onsubmit="return confirm('¿Cerrar la caja con este recuento?')"
                          class="space-y-4 rounded-2xl border border-slate-200 bg-white p-5 shadow-sm">
                        @csrf

                        <div class="flex flex-wrap items-end justify-between gap-3">
                            <div>
                                <h2 class="text-base font-semibold text-slate-900">Recuento — lo que tenés</h2>
                                <p class="text-sm text-slate-500">Completá un importe por medio. Cero también cuenta (si no usaste ese medio).</p>
                            </div>
                            <details class="w-full rounded-xl border border-slate-200 bg-slate-50 p-3 text-sm sm:w-auto sm:min-w-[20rem]">
                                <summary class="cursor-pointer font-medium text-slate-700">Contador de billetes</summary>
                                <div class="mt-3 grid grid-cols-2 gap-2">
                                    <template x-for="b in billetes" :key="b.valor">
                                        <label class="flex items-center gap-2 text-xs">
                                            <span class="w-14 font-mono">$<span x-text="b.valor"></span></span>
                                            <input type="number" min="0" step="1" x-model.number="b.cantidad"
                                                   class="w-full rounded border border-slate-300 bg-white px-2 py-1">
                                        </label>
                                    </template>
                                </div>
                                <div class="mt-3 flex items-center justify-between gap-2">
                                    <span class="text-sm font-semibold">Total: $<span x-text="totalBilletes().toLocaleString('es-AR', {minimumFractionDigits: 2, maximumFractionDigits: 2})"></span></span>
                                    <button type="button" @click="copiarBilletesAEfectivo()"
                                            class="rounded-lg bg-slate-800 px-3 py-1.5 text-xs font-medium text-white">
                                        Copiar a efectivo
                                    </button>
                                </div>
                            </details>
                        </div>

                        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                            @foreach ($mediosCierre as $medio)
                                @php($esEfectivo = ($medio['tipo'] ?? '') === 'efectivo')
                                <label class="flex flex-col rounded-xl border border-slate-200 p-4 {{ $esEfectivo ? 'sm:col-span-2 bg-slate-50' : 'bg-white' }}">
                                    <span class="flex items-center justify-between gap-2">
                                        <span class="text-sm font-semibold text-slate-800">{{ $medio['nombre'] }}</span>
                                        @if ($esEfectivo)
                                            <span class="rounded-full bg-slate-900 px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-white">Cajón</span>
                                        @endif
                                    </span>
                                    <span class="mt-1 text-xs text-slate-500">{{ $hintMedio($medio['tipo'] ?? '', $medio['nombre']) }}</span>
                                    <span class="relative mt-3">
                                        <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">$</span>
                                        <input type="number" step="0.01" min="0"
                                               name="declarado[{{ $medio['medio_pago_id'] }}]"
                                               @if ($esEfectivo) x-model="efectivoContado" @endif
                                               placeholder="0,00"
                                               class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-8 pr-3 text-lg font-semibold tabular-nums focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                               value="{{ old('declarado.'.$medio['medio_pago_id'], '') }}">
                                    </span>
                                </label>
                            @endforeach
                        </div>

                        <div class="rounded-xl border border-dashed border-slate-300 bg-slate-50 p-4">
                            <div class="flex flex-wrap items-center justify-between gap-2">
                                <label class="text-sm font-semibold text-slate-800" for="apertura_siguiente_monto">
                                    Cambio próximo turno
                                </label>
                                <button type="button" @click="usarEfectivoComoCambio()"
                                        class="text-xs font-medium text-indigo-600 hover:underline">
                                    Usar el efectivo que conté →
                                </button>
                            </div>
                            <p class="mt-1 text-xs text-slate-500">Plata que <strong>deja</strong> en el cajón para el que abre después. No reemplaza el recuento de arriba.</p>
                            <span class="relative mt-3 block max-w-sm">
                                <span class="pointer-events-none absolute inset-y-0 left-3 flex items-center text-slate-400">$</span>
                                <input type="number" step="0.01" min="0" name="apertura_siguiente_monto" id="apertura_siguiente_monto"
                                       x-ref="cambioProximo"
                                       placeholder="0,00"
                                       class="w-full rounded-lg border border-slate-300 bg-white py-3 pl-8 pr-3 text-lg font-semibold tabular-nums focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200"
                                       value="{{ old('apertura_siguiente_monto', '') }}">
                            </span>
                        </div>

                        <textarea name="observaciones" rows="2" placeholder="Observaciones (opcional)"
                                  class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">{{ old('observaciones') }}</textarea>

                        <button class="w-full rounded-xl bg-indigo-600 py-3.5 text-sm font-semibold text-white hover:bg-indigo-700">
                            Guardar cierre y ver esperado
                        </button>
                    </form>
                @endcan
            @endif

            @if ($sesion->estado === 'cerrada')
                <div id="ticket-cierre" class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:border-0 print:shadow-none">
                    <div class="mb-4 flex flex-wrap items-center justify-between gap-2">
                        <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Ticket cierre de caja</h2>
                        <button type="button" onclick="window.print()"
                                class="rounded-lg border border-slate-300 px-3 py-1.5 text-xs font-medium hover:bg-slate-50 print:hidden">
                            Imprimir
                        </button>
                    </div>
                    <p class="text-xs text-slate-500">
                        {{ $sesion->caja->nombre }} · {{ $sesion->usuario->name }} ·
                        {{ $sesion->cerrada_at?->format('d/m/Y H:i') }}
                    </p>

                    <h3 class="mb-2 mt-4 text-xs font-bold uppercase text-slate-500">Detalle (sistema)</h3>
                    <table class="mb-4 w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs text-slate-500">
                                <th class="py-1">Medio</th>
                                <th class="py-1 text-right">Ingresos</th>
                                <th class="py-1 text-right">Egresos</th>
                                <th class="py-1 text-right">Esperado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($sesion->detalle_sistema ?? []) as $fila)
                                @if (($fila['tipo'] ?? '') === 'efectivo' && (float) ($fila['apertura'] ?? 0) != 0)
                                    <tr class="border-b border-slate-50">
                                        <td class="py-1.5">Saldo inicio (Efectivo)</td>
                                        <td class="py-1.5 text-right">$ {{ number_format((float) $fila['apertura'], 2, ',', '.') }}</td>
                                        <td class="py-1.5 text-right">—</td>
                                        <td class="py-1.5 text-right">—</td>
                                    </tr>
                                @endif
                                <tr class="border-b border-slate-50">
                                    <td class="py-1.5">{{ $fila['nombre'] }}</td>
                                    <td class="py-1.5 text-right">$ {{ number_format((float) ($fila['ingresos'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="py-1.5 text-right">$ {{ number_format((float) ($fila['egresos'] ?? 0), 2, ',', '.') }}</td>
                                    <td class="py-1.5 text-right font-medium">$ {{ number_format((float) ($fila['esperado'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h3 class="mb-2 text-xs font-bold uppercase text-slate-500">Recuento manual</h3>
                    <table class="mb-4 w-full text-sm">
                        <thead>
                            <tr class="border-b text-left text-xs text-slate-500">
                                <th class="py-1">Medio</th>
                                <th class="py-1 text-right">Contado</th>
                            </tr>
                        </thead>
                        <tbody>
                            @foreach (($sesion->detalle_declarado ?? []) as $fila)
                                <tr class="border-b border-slate-50">
                                    <td class="py-1.5">{{ $fila['nombre'] }}</td>
                                    <td class="py-1.5 text-right font-medium">$ {{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}</td>
                                </tr>
                            @endforeach
                        </tbody>
                    </table>

                    <h3 class="mb-2 text-xs font-bold uppercase text-slate-500">Diferencias</h3>
                    @php($difs = $sesion->detalle_diferencias ?? [])
                    @if (count($difs) === 0)
                        <p class="text-sm font-medium text-emerald-600">Sin diferencias</p>
                    @else
                        <table class="w-full text-sm">
                            <thead>
                                <tr class="border-b text-left text-xs text-slate-500">
                                    <th class="py-1">Medio</th>
                                    <th class="py-1 text-right">Dif. (esperado − contado)</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($difs as $fila)
                                    <tr class="border-b border-slate-50">
                                        <td class="py-1.5">{{ $fila['nombre'] }}</td>
                                        <td class="py-1.5 text-right font-semibold text-red-600">
                                            $ {{ number_format((float) ($fila['importe'] ?? 0), 2, ',', '.') }}
                                            <span class="text-xs font-normal text-slate-500">
                                                ({{ (float) ($fila['importe'] ?? 0) > 0 ? 'faltante' : 'sobrante' }})
                                            </span>
                                        </td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    @endif

                    @if ($sesion->observaciones)
                        <p class="mt-4 text-xs text-slate-500"><span class="font-semibold">Obs:</span> {{ $sesion->observaciones }}</p>
                    @endif

                    <div class="mt-10 hidden border-t border-dashed border-slate-300 pt-8 text-center text-xs text-slate-500 print:block">
                        ------------------------------------------------------<br>
                        Firma y sello de responsable
                    </div>
                </div>
            @endif

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
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

            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm print:hidden">
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

    <script>
        function cierreCiego() {
            return {
                efectivoContado: '',
                billetes: [
                    { valor: 10000, cantidad: 0 },
                    { valor: 2000, cantidad: 0 },
                    { valor: 1000, cantidad: 0 },
                    { valor: 500, cantidad: 0 },
                    { valor: 200, cantidad: 0 },
                    { valor: 100, cantidad: 0 },
                    { valor: 50, cantidad: 0 },
                    { valor: 20, cantidad: 0 },
                    { valor: 10, cantidad: 0 },
                ],
                totalBilletes() {
                    return this.billetes.reduce((s, b) => s + (Number(b.valor) * Number(b.cantidad || 0)), 0);
                },
                copiarBilletesAEfectivo() {
                    this.efectivoContado = this.totalBilletes().toFixed(2);
                },
                usarEfectivoComoCambio() {
                    const n = Number(this.efectivoContado || 0);
                    if (this.$refs.cambioProximo) {
                        this.$refs.cambioProximo.value = n.toFixed(2);
                    }
                },
            };
        }
    </script>
    <style>
        @media print {
            aside, nav, header { display: none !important; }
            main, #ticket-cierre { width: 100% !important; max-width: none !important; }
        }
        @keyframes flecha-cierre {
            0%, 100% { transform: translateX(0); opacity: .35; }
            50% { transform: translateX(7px); opacity: 1; }
        }
        .flecha-cierre {
            animation: flecha-cierre 1.15s ease-in-out infinite;
            display: inline-block;
        }
        @keyframes esperado-oculto {
            0%, 100% { filter: blur(0.4px); }
            50% { filter: blur(2px); }
        }
        .esperado-oculto {
            animation: esperado-oculto 2s ease-in-out infinite;
        }
    </style>
@endsection
