<div class="space-y-4 sm:col-span-2">
    <details class="rounded-xl border border-slate-200 bg-white shadow-sm" open>
        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-indigo-700">+ Factura recibida</summary>
        <form method="POST" action="{{ route('proveedores.cuenta.factura', $titular) }}"
              x-data="{ previo: 0, desc: 0, iva: 0 }"
              class="space-y-3 border-t border-slate-100 p-5">
            @csrf
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Fecha</label>
                    <input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Nº factura</label>
                    <input type="text" name="factura_numero" required placeholder="0001-00000241" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Descripción</label>
                    <input type="text" name="concepto" placeholder="Opcional" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>
            <div class="grid grid-cols-2 gap-3 sm:grid-cols-5">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Neto previo</label>
                    <input type="number" step="0.01" min="0" name="neto_previo" x-model.number="previo" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Descuento</label>
                    <input type="number" step="0.01" min="0" name="descuento" x-model.number="desc" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Neto</label>
                    <input type="number" step="0.01" name="neto" readonly
                           :value="Math.max(0, (previo || 0) - (desc || 0)).toFixed(2)"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">IVA</label>
                    <input type="number" step="0.01" min="0" name="iva" x-model.number="iva" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Total</label>
                    <input type="number" step="0.01" name="total" readonly
                           :value="(Math.max(0, (previo || 0) - (desc || 0)) + (iva || 0)).toFixed(2)"
                           class="w-full rounded-lg border border-slate-200 bg-slate-50 px-3 py-2 text-sm font-semibold">
                </div>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Guardar factura</button>
        </form>
    </details>

    <details class="rounded-xl border border-slate-200 bg-white shadow-sm">
        <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-emerald-700">+ Pago al proveedor</summary>
        <form method="POST" action="{{ route('proveedores.cuenta.pago', $titular) }}"
              x-data="{
                  aplicar: false,
                  sujeto: 0,
                  alicuota: {{ $alicuotaProveedor ?: 0 }},
                  retenido: 0,
                  neto: 0,
                  total: 0,
                  recalc() {
                      this.retenido = Math.round((this.sujeto || 0) * (this.alicuota || 0) / 100 * 100) / 100;
                      this.neto = Math.max(0, (this.sujeto || 0) - this.retenido);
                      this.total = this.neto + this.retenido;
                  }
              }"
              class="space-y-3 border-t border-slate-100 p-5">
            @csrf
            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Fecha pago</label>
                    <input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Descripción</label>
                    <input type="text" name="concepto" placeholder="Pago cuenta corriente" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
            </div>

            @if ($agenteRetencion ?? false)
                <div class="rounded-lg border border-amber-200 bg-amber-50 p-4">
                    <label class="flex items-center gap-2 text-sm font-medium text-amber-900">
                        <input type="checkbox" name="aplicar_retencion" value="1" x-model="aplicar" @change="recalc()" class="rounded border-amber-300">
                        Aplicar retención de Ingresos Brutos
                    </label>
                    <div x-show="aplicar" x-cloak class="mt-3 grid grid-cols-2 gap-3 sm:grid-cols-4">
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Nº factura SIRCAR</label>
                            <input type="text" name="factura_numero" placeholder="0001-00000241" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Fecha retención</label>
                            <input type="date" name="fecha_retencion" value="{{ now()->format('Y-m-d') }}" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Monto sujeto</label>
                            <input type="number" step="0.01" min="0" name="monto_sujeto" x-model.number="sujeto" @input="recalc()" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Alícuota %</label>
                            <input type="number" step="0.01" min="0" name="alicuota" x-model.number="alicuota" @input="recalc()" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Monto retenido</label>
                            <input type="number" step="0.01" name="monto_retencion" x-model="retenido" readonly class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Neto pagado (caja)</label>
                            <input type="number" step="0.01" name="monto_neto" x-model="neto" readonly class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm">
                        </div>
                        <div>
                            <label class="mb-1 block text-xs font-medium text-slate-600">Total cta. cte.</label>
                            <input type="number" step="0.01" x-model="total" readonly class="w-full rounded-lg border border-slate-200 bg-white px-3 py-2 text-sm font-semibold">
                        </div>
                    </div>
                </div>
            @else
                <div x-show="!aplicar">
                    <label class="mb-1 block text-xs font-medium text-slate-600">Importe del pago</label>
                    <input type="number" step="0.01" min="0.01" name="importe" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-xs">
                </div>
            @endif

            @unless ($agenteRetencion ?? false)
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Importe del pago</label>
                    <input type="number" step="0.01" min="0.01" name="importe" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm sm:max-w-xs">
                </div>
            @endunless

            <div class="grid grid-cols-1 gap-3 sm:grid-cols-2">
                <div>
                    <label class="mb-1 block text-xs font-medium text-slate-600">Medio de pago</label>
                    <select name="medio_pago_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">— Seleccionar —</option>
                        @foreach ($mediosPago as $medio)
                            <option value="{{ $medio->id }}">{{ $medio->nombre }}</option>
                        @endforeach
                    </select>
                </div>
                <label class="flex items-center gap-2 self-end pb-2 text-sm text-slate-600">
                    <input type="checkbox" name="bonificacion" value="1" class="rounded border-slate-300">
                    Bonificación (sin egreso de caja)
                </label>
            </div>

            <button class="rounded-lg bg-emerald-600 px-4 py-2 text-sm font-semibold text-white hover:bg-emerald-700">Registrar pago</button>
        </form>
    </details>
</div>
