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

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Concepto</th>
                    <th class="px-4 py-3 text-right">Importe</th>
                    <th class="px-4 py-3">Conciliado</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movimientos as $mov)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">{{ $mov->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">{{ $mov->concepto }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ (float) $mov->importe < 0 ? 'text-red-600' : 'text-emerald-700' }}">
                            $ {{ number_format((float) $mov->importe, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3">{{ $mov->conciliado ? 'Sí' : 'No' }}</td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-10 text-center text-slate-400">Sin movimientos.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <a href="{{ route('bancos.index') }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Cuentas</a>
        {{ $movimientos->links() }}
    </div>
@endsection
