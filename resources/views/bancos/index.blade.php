@extends('layouts.app')

@section('titulo', 'Cuentas bancarias')

@section('contenido')
    <div class="mb-4 grid grid-cols-1 gap-4 lg:grid-cols-3">
        <div class="lg:col-span-2 overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-4 py-3">Nombre</th>
                        <th class="px-4 py-3">Banco</th>
                        <th class="px-4 py-3">CBU / Alias</th>
                        <th class="px-4 py-3">Moneda</th>
                        <th class="px-4 py-3">Estado</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($cuentas as $cuenta)
                        <tr class="cursor-pointer hover:bg-slate-50" onclick="window.location='{{ route('bancos.show', $cuenta) }}'">
                            <td class="px-4 py-3 font-medium text-indigo-600">{{ $cuenta->nombre }}</td>
                            <td class="px-4 py-3">{{ $cuenta->banco ?? '—' }}</td>
                            <td class="px-4 py-3 font-mono text-xs">{{ $cuenta->cbu ?: ($cuenta->alias ?: '—') }}</td>
                            <td class="px-4 py-3">{{ $cuenta->moneda }}</td>
                            <td class="px-4 py-3">
                                @if ($cuenta->activa)
                                    <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                                @else
                                    <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs text-slate-500">Inactiva</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-10 text-center text-slate-400">Todavía no hay cuentas.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>

        @can('bancos.gestionar')
            <form method="POST" action="{{ route('bancos.store') }}" class="space-y-3 rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                @csrf
                <p class="text-sm font-semibold">Nueva cuenta</p>
                <input type="text" name="nombre" placeholder="Nombre *" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="banco" placeholder="Banco" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="cbu" placeholder="CBU" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="alias" placeholder="Alias" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <input type="text" name="moneda" value="ARS" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button class="w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">Crear</button>
            </form>
        @endcan
    </div>
@endsection
