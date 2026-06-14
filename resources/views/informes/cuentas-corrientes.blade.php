@extends('layouts.app')

@section('titulo', 'Cuentas corrientes')

@section('contenido')
    <form method="GET" class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <div>
            <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Filtro</label>
            <select name="filtro" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <option value="">Todos los clientes</option>
                <option value="con_saldo" {{ request('filtro') === 'con_saldo' ? 'selected' : '' }}>Con deuda (saldo &gt; 0)</option>
                <option value="a_favor" {{ request('filtro') === 'a_favor' ? 'selected' : '' }}>Saldo a favor (&lt; 0)</option>
            </select>
        </div>
        <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Aplicar</button>
    </form>

    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-2">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Saldo neto clientes</p>
            <p class="mt-1 text-2xl font-bold {{ $totales['saldo_neto'] > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                $ {{ number_format($totales['saldo_neto'], 2, ',', '.') }}
            </p>
        </div>
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-xs font-semibold uppercase tracking-wider text-slate-500">Clientes listados</p>
            <p class="mt-1 text-2xl font-bold">{{ number_format($totales['clientes_listados'], 0, ',', '.') }}</p>
        </div>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3 text-right">Saldo</th>
                    <th class="px-4 py-3"></th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($clientes as $cliente)
                    <tr>
                        <td class="px-4 py-3 font-medium">{{ $cliente->nombre }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $cliente->documento ?: '—' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ (float) $cliente->saldo > 0 ? 'text-red-600' : 'text-slate-700' }}">
                            $ {{ number_format((float) $cliente->saldo, 2, ',', '.') }}
                        </td>
                        <td class="px-4 py-3 text-right">
                            <a href="{{ route('clientes.cuenta', $cliente) }}" class="text-indigo-600 hover:text-indigo-800">Ver cuenta</a>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">Sin movimientos para mostrar.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
@endsection
