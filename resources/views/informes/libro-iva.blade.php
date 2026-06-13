@extends('layouts.app')

@section('titulo', 'Libro IVA Ventas')

@section('contenido')
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
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
        <a href="{{ request()->fullUrlWithQuery(['exportar' => 'csv']) }}"
           class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm font-medium hover:bg-slate-50">
            Exportar CSV
        </a>
    </form>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Comprobante</th>
                    <th class="px-4 py-3">Receptor</th>
                    <th class="px-4 py-3 text-right">Neto</th>
                    <th class="px-4 py-3 text-right">IVA</th>
                    <th class="px-4 py-3 text-right">Total</th>
                    <th class="px-4 py-3">CAE</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($comprobantes as $c)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">{{ $c->fecha_emision->format('d/m/Y') }}</td>
                        <td class="px-4 py-2.5">
                            <span class="font-medium">{{ $c->tipoNombre() }}</span>
                            <span class="ml-1 font-mono text-xs text-slate-400">{{ $c->numeroFormateado() }}</span>
                        </td>
                        <td class="px-4 py-2.5">{{ $c->receptor_nombre }}
                            @if ($c->doc_numero !== '0')<span class="text-xs text-slate-400">({{ $c->doc_numero }})</span>@endif
                        </td>
                        <td class="px-4 py-2.5 text-right">$ {{ number_format((float) $c->neto, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right">$ {{ number_format((float) $c->iva, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 text-right font-semibold">$ {{ number_format((float) $c->total, 2, ',', '.') }}</td>
                        <td class="px-4 py-2.5 font-mono text-xs">{{ $c->cae }}</td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-10 text-center text-slate-400">No hay comprobantes autorizados en el período.</td></tr>
                @endforelse
            </tbody>
            @if ($comprobantes->isNotEmpty())
                <tfoot class="bg-slate-50 font-semibold">
                    <tr>
                        <td colspan="3" class="px-4 py-3 text-right">Totales</td>
                        <td class="px-4 py-3 text-right">$ {{ number_format($totales['neto'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right">$ {{ number_format($totales['iva'], 2, ',', '.') }}</td>
                        <td class="px-4 py-3 text-right text-indigo-600">$ {{ number_format($totales['total'], 2, ',', '.') }}</td>
                        <td></td>
                    </tr>
                </tfoot>
            @endif
        </table>
    </div>
@endsection
