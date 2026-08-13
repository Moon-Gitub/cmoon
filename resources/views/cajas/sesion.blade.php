@extends('layouts.app')

@section('titulo', "Sesión de {$sesion->caja->nombre}")

@section('contenido')
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
                    @else
                        <p class="rounded-lg bg-amber-50 px-3 py-2 text-xs text-amber-800">
                            Cierre ciego: al cerrar cargás lo que contás por medio. El sistema compara después.
                        </p>
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

                    <div class="space-y-3 rounded-xl border border-amber-200 bg-amber-50 p-5">
                        <h2 class="text-sm font-semibold text-amber-800">Cerrar caja — recuento manual</h2>
                        <p class="text-xs text-amber-700">Ingresá lo que tenés en cada medio. No se muestra el esperado hasta guardar.</p>

                        {{-- Contador de billetes (como demonew) --}}
                        <details class="rounded-lg border border-amber-200 bg-white p-3 text-sm">
                            <summary class="cursor-pointer font-medium text-slate-700">Contador de billetes (efectivo)</summary>
                            <div class="mt-3 grid grid-cols-2 gap-2">
                                <template x-for="b in billetes" :key="b.valor">
                                    <label class="flex items-center gap-2 text-xs">
                                        <span class="w-14 font-mono">$<span x-text="b.valor"></span></span>
                                        <input type="number" min="0" step="1" x-model.number="b.cantidad"
                                               class="w-full rounded border border-slate-300 px-2 py-1">
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

                        <form method="POST" action="{{ route('cajas.cerrar', $sesion) }}"
                              onsubmit="return confirm('¿Cerrar la caja con este recuento?')"
                              class="space-y-2">
                            @csrf
                            @foreach ($mediosCierre as $medio)
                                <label class="block text-xs font-medium text-slate-600">{{ $medio['nombre'] }}</label>
                                <input type="number" step="0.01" min="0"
                                       name="declarado[{{ $medio['medio_pago_id'] }}]"
                                       @if (($medio['tipo'] ?? '') === 'efectivo') x-model="efectivoContado" @endif
                                       placeholder="0,00"
                                       class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm"
                                       value="{{ old('declarado.'.$medio['medio_pago_id'], '') }}">
                            @endforeach

                            <label class="block pt-2 text-xs font-medium text-slate-600">Cambio próximo turno (efectivo)</label>
                            <input type="number" step="0.01" min="0" name="apertura_siguiente_monto"
                                   placeholder="Queda en caja para el siguiente turno"
                                   class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm"
                                   value="{{ old('apertura_siguiente_monto', '') }}">

                            <textarea name="observaciones" rows="2" placeholder="Observaciones (opcional)"
                                      class="w-full rounded-lg border border-amber-200 bg-white px-3 py-2 text-sm">{{ old('observaciones') }}</textarea>

                            <button class="w-full rounded-lg bg-amber-600 py-2.5 text-sm font-semibold text-white hover:bg-amber-700">
                                Guardar cierre y comparar
                            </button>
                        </form>
                    </div>
                @endcan
            @endif

            <a href="{{ route('cajas.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver a cajas</a>
        </div>

        <div class="space-y-4 lg:col-span-2">
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
            };
        }
    </script>
    <style>
        @media print {
            aside, nav, header { display: none !important; }
            main, #ticket-cierre { width: 100% !important; max-width: none !important; }
        }
    </style>
@endsection
