@extends('layouts.app')

@section('titulo', 'Libro IVA Compras')

@section('contenido')
    @include('informes._nav')

    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <x-sort-hidden :sort="$sort ?? null" :dir="$dir ?? null" />
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
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Exportar CSV
        </a>
    </form>

    <p class="mb-3 text-xs text-slate-500">
        Neto e IVA estimados con alícuota 21% (neto = total / 1,21) porque las compras no tienen desglose fiscal propio.
    </p>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <x-sortable-th column="fecha" label="Fecha" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="desc" />
                    <x-sortable-th column="proveedor" label="Proveedor" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                    <x-sortable-th column="factura" label="Factura" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                    <th class="px-4 py-3 text-right">Neto</th>
                    <th class="px-4 py-3 text-right">IVA 21%</th>
                    <x-sortable-th column="total" label="Total" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="desc" align="right" />
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($compras as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">{{ $c->fecha?->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5">
                            {{ $c->proveedor }}
                            @if ($c->documento)
                                <span class="text-xs text-slate-400">({{ $c->documento }})</span>
                            @endif
                        </td>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $c->factura_numero ?: '—' }}</td>
                        <td class="px-4 py-2.5 text-right">$ {{ number_format($c->neto, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right">$ {{ number_format($c->iva, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold">$ {{ number_format($c->total, 2, ',', '.') }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-10 text-center text-slate-400">No hay compras completadas en el período.</td></tr>
                @endforelse
            </tbody>
            @if ($compras->isNotEmpty())
                <tfoot class="bg-slate-50 font-semibold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right">Totales</td>
                        <td class="px-4 py-3 text-right">$ {{ number_format($totales['neto'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">$ {{ number_format($totales['iva'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-indigo-600">$ {{ number_format($totales['total'], 2, ',', '.') }}</td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
