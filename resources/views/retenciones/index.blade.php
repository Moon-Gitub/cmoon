@extends('layouts.app')

@section('titulo', 'Retenciones IIBB (SIRCAR)')

@section('contenido')
    @can('retenciones.gestionar')
        <details class="mb-4 rounded-xl border border-slate-200 bg-white shadow-sm" {{ $errors->any() ? 'open' : '' }}>
            <summary class="cursor-pointer px-5 py-3 text-sm font-semibold text-indigo-700">+ Registrar retención</summary>
            <form method="POST" action="{{ route('retenciones.store') }}"
                  x-data="{ neto: {{ old('factura_neto', 0) }}, alicuota: {{ old('alicuota', 1.25) }} }"
                  class="grid grid-cols-1 gap-4 border-t border-slate-100 p-5 sm:grid-cols-2 lg:grid-cols-4">
                @csrf
                <div class="sm:col-span-2">
                    <label class="mb-1 block text-sm font-medium text-slate-700">Proveedor *</label>
                    <select name="proveedor_id" required
                            @change="alicuota = $event.target.selectedOptions[0]?.dataset.alicuota || alicuota"
                            class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <option value="">Seleccionar…</option>
                        @foreach ($proveedores as $proveedor)
                            <option value="{{ $proveedor->id }}" data-alicuota="{{ $proveedor->alicuota_retencion_iibb }}">
                                {{ $proveedor->razon_social }} {{ $proveedor->cuit ? "({$proveedor->cuit})" : '' }}
                            </option>
                        @endforeach
                    </select>
                    @error('proveedor_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Factura proveedor *</label>
                    <input type="text" name="factura_numero" value="{{ old('factura_numero') }}" required
                           placeholder="0001-00001234" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('factura_numero')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Fecha *</label>
                    <input type="date" name="fecha" value="{{ old('fecha', now()->format('Y-m-d')) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Neto factura *</label>
                    <input type="number" name="factura_neto" step="0.01" min="0.01" x-model.number="neto" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    @error('factura_neto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Alícuota % *</label>
                    <input type="number" name="alicuota" step="0.001" min="0.001" x-model.number="alicuota" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Régimen</label>
                    <input type="number" name="regimen" value="{{ old('regimen', 101) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Jurisdicción</label>
                    <input type="number" name="jurisdiccion" value="{{ old('jurisdiccion', 913) }}" required
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <p class="mt-1 text-xs text-slate-400">913 = Mendoza</p>
                </div>
                <div class="flex items-end justify-between gap-3 sm:col-span-2 lg:col-span-4">
                    <p class="text-sm text-slate-600">Monto a retener:
                        <span class="text-lg font-bold text-indigo-600"
                              x-text="'$ ' + ((neto || 0) * (alicuota || 0) / 100).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 })"></span>
                    </p>
                    <button class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Registrar retención
                    </button>
                </div>
            </form>
        </details>
    @endcan

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Desde</label>
            <input type="date" name="desde" value="{{ $desde->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Hasta</label>
            <input type="date" name="hasta" value="{{ $hasta->format('Y-m-d') }}"
                   class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Filtrar</button>
        <a href="{{ route('retenciones.txt', ['desde' => $desde->format('Y-m-d'), 'hasta' => $hasta->format('Y-m-d')]) }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Exportar TXT SIRCAR
        </a>
        <p class="ml-auto text-sm text-slate-500">Total retenido:
            <span class="text-lg font-bold text-indigo-600">$ {{ number_format((float) $totalPeriodo, 2, ',', '.') }}</span>
        </p>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Proveedor</th>
                    <th class="px-4 py-3">Factura</th>
                    <th class="px-4 py-3 text-right">Neto</th>
                    <th class="px-4 py-3 text-right">Alícuota</th>
                    <th class="px-4 py-3 text-right">Retenido</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($retenciones as $retencion)
                    <tr class="hover:bg-slate-50 {{ $retencion->anulada ? 'opacity-50' : '' }}">
                        <td class="px-4 py-2.5">{{ $retencion->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5">
                            {{ $retencion->proveedor->razon_social }}
                            <span class="text-xs text-slate-400">{{ $retencion->proveedor->cuit }}</span>
                            @if ($retencion->anulada)
                                <span class="ml-1 rounded-full bg-red-50 px-2 py-0.5 text-xs font-medium text-red-600">Anulada</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $retencion->factura_numero }}</td>
                        <td class="px-4 py-2.5 text-right">$ {{ number_format((float) $retencion->factura_neto, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right">{{ rtrim(rtrim(number_format((float) $retencion->alicuota, 3, ',', ''), '0'), ',') }}%</td>
                        <td class="px-4 py-2.5 text-right font-semibold">$ {{ number_format((float) $retencion->monto, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right">
                            @can('retenciones.gestionar')
                                <form method="POST" action="{{ route('retenciones.anular', $retencion) }}" class="inline"
                                      onsubmit="return confirm('¿{{ $retencion->anulada ? 'Restaurar' : 'Anular' }} esta retención?')">
                                    @csrf
                                    <button class="text-sm font-medium {{ $retencion->anulada ? 'text-emerald-600 hover:text-emerald-800' : 'text-red-500 hover:text-red-700' }}">
                                        {{ $retencion->anulada ? 'Restaurar' : 'Anular' }}
                                    </button>
                                </form>
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No hay retenciones en el período.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $retenciones->links() }}</div>
@endsection
