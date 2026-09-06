@extends('layouts.app')

@section('titulo', $cuenta->nombre)

@section('contenido')
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm lg:col-span-2">
            <dl class="grid grid-cols-2 gap-3 text-sm sm:grid-cols-4">
                <div><dt class="text-xs uppercase text-slate-500">Banco</dt><dd class="font-medium">{{ $cuenta->banco ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">CBU</dt><dd class="font-mono text-xs">{{ $cuenta->cbu ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Alias</dt><dd class="font-medium">{{ $cuenta->alias ?? '—' }}</dd></div>
                <div><dt class="text-xs uppercase text-slate-500">Moneda</dt><dd class="font-medium">{{ $cuenta->moneda }}</dd></div>
            </dl>
        </div>
        <form method="POST" action="{{ route('bancos.importar', $cuenta) }}" enctype="multipart/form-data"
              class="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            @csrf
            <p class="text-sm font-semibold">Importar CSV</p>
            <p class="text-xs text-slate-500">Columnas: fecha;concepto;importe</p>
            <input type="file" name="archivo" accept=".csv,.txt" required class="w-full text-sm">
            <button class="w-full rounded-lg bg-slate-800 py-2 text-sm font-semibold text-white hover:bg-slate-900">Importar</button>
        </form>
    </div>

    <div class="mb-3 flex flex-wrap items-center gap-3">
        <a href="{{ route('bancos.show', ['cuentaBancaria' => $cuenta, 'solo_pendientes' => 1]) }}"
           class="rounded-lg border px-3 py-1.5 text-sm {{ $soloPendientes ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
            Solo pendientes
        </a>
        <a href="{{ route('bancos.show', $cuenta) }}"
           class="rounded-lg border px-3 py-1.5 text-sm {{ ! $soloPendientes ? 'border-indigo-300 bg-indigo-50 text-indigo-700' : 'border-slate-300 text-slate-600 hover:bg-slate-50' }}">
            Todos
        </a>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Concepto</th>
                    <th class="px-4 py-3 text-right">Importe</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movimientos as $mov)
                    @php
                        $importeAbs = abs((float) $mov->importe);
                        $sugeridas = $ventasRecientes->filter(function ($v) use ($mov, $importeAbs) {
                            $diffImporte = abs((float) $v->total - $importeAbs);
                            $diffDias = abs($v->fecha->startOfDay()->diffInDays($mov->fecha->copy()->startOfDay()));

                            return $diffImporte <= 0.01 && $diffDias <= 3;
                        });
                    @endphp
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $mov->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $mov->concepto }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ (float) $mov->importe < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            $ {{ number_format((float) $mov->importe, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($mov->conciliado)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Conciliado</span>
                            @else
                                <span class="rounded-full bg-amber-50 px-2 py-0.5 text-xs font-medium text-amber-700">Pendiente</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            @can('bancos.gestionar')
                                @if ($mov->conciliado)
                                    <form method="POST" action="{{ route('bancos.desconciliar', [$cuenta, $mov]) }}" class="inline">
                                        @csrf
                                        <button class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">
                                            Desconciliar
                                        </button>
                                    </form>
                                @else
                                    <form method="POST" action="{{ route('bancos.conciliar', [$cuenta, $mov]) }}"
                                          class="inline-flex flex-wrap items-center justify-end gap-1">
                                        @csrf
                                        <select name="venta_id" class="max-w-[12rem] rounded border border-slate-300 px-1 py-1 text-xs">
                                            <option value="">Sin vincular</option>
                                            @foreach ($sugeridas as $v)
                                                <option value="{{ $v->id }}">
                                                    ★ #{{ $v->numero }} — $ {{ number_format((float) $v->total, 2, ',', '.') }}
                                                </option>
                                            @endforeach
                                            @foreach ($ventasRecientes->take(15) as $v)
                                                @continue($sugeridas->contains('id', $v->id))
                                                <option value="{{ $v->id }}">
                                                    #{{ $v->numero }} {{ $v->fecha->format('d/m') }} — $ {{ number_format((float) $v->total, 2, ',', '.') }}
                                                </option>
                                            @endforeach
                                        </select>
                                        <button class="rounded-lg bg-indigo-600 px-2.5 py-1 text-xs font-semibold text-white hover:bg-indigo-700">
                                            Conciliar
                                        </button>
                                    </form>
                                @endif
                            @endcan
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Sin movimientos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <a href="{{ route('bancos.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Cuentas</a>
        {{ $movimientos->links() }}
    </div>
@endsection
