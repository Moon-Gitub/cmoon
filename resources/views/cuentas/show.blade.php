@extends('layouts.app')

@section('titulo', "Cuenta corriente: {$nombre}")

@section('contenido')
    <div class="mb-6 grid grid-cols-1 gap-4 sm:grid-cols-3">
        <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
            <p class="text-sm text-slate-500">{{ $esCliente ? 'Saldo del cliente' : 'Saldo con el proveedor' }}</p>
            <p class="mt-1 text-3xl font-bold {{ $saldo > 0 ? 'text-red-600' : 'text-emerald-600' }}">
                $ {{ number_format(abs($saldo), 2, ',', '.') }}
            </p>
            <p class="mt-1 text-xs text-slate-400">
                @if (round($saldo, 2) == 0)
                    Sin deuda
                @elseif ($esCliente)
                    {{ $saldo > 0 ? 'El cliente debe' : 'Saldo a favor del cliente' }}
                @else
                    {{ $saldo > 0 ? 'Le debemos al proveedor' : 'Saldo a nuestro favor' }}
                @endif
            </p>
        </div>

        @can('cuentas.registrar')
            <form method="POST"
                  action="{{ $esCliente ? route('clientes.cuenta.registrar', $titular) : route('proveedores.cuenta.registrar', $titular) }}"
                  class="space-y-2 rounded-xl border border-slate-200 bg-white p-5 shadow-sm sm:col-span-2">
                @csrf
                <p class="text-sm font-semibold">Registrar movimiento</p>
                <div class="grid grid-cols-2 gap-2 sm:grid-cols-4">
                    <select name="tipo" required class="rounded-lg border border-slate-300 px-2 py-2 text-sm">
                        <option value="factura">{{ $esCliente ? 'Cargo / factura' : 'Factura recibida' }}</option>
                        <option value="pago">{{ $esCliente ? 'Pago del cliente' : 'Pago al proveedor' }}</option>
                        <option value="ajuste">Ajuste</option>
                    </select>
                    <input type="text" name="concepto" placeholder="Concepto" required
                           class="rounded-lg border border-slate-300 px-3 py-2 text-sm sm:col-span-2">
                    <input type="number" step="0.01" min="0.01" name="importe" placeholder="Importe $" required
                           class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                </div>
                <div class="flex items-center justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <input type="date" name="fecha" value="{{ now()->format('Y-m-d') }}" required
                               class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                        <label class="flex items-center gap-1.5 text-xs text-slate-600">
                            <input type="checkbox" name="resta" value="1" class="rounded border-slate-300">
                            Si es ajuste, resta deuda
                        </label>
                    </div>
                    <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Registrar
                    </button>
                </div>
                @error('importe')<p class="text-xs text-red-600">{{ $message }}</p>@enderror
            </form>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Concepto</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3 text-right">Importe</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($movimientos as $mov)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 text-slate-600">{{ $mov->fecha->format('d/m/Y') }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium capitalize text-slate-700">{{ $mov->tipo }}</span>
                        </td>
                        <td class="px-4 py-3">{{ $mov->concepto }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $mov->usuario?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ (float) $mov->importe >= 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            {{ (float) $mov->importe >= 0 ? '+' : '−' }} $ {{ number_format(abs((float) $mov->importe), 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">Sin movimientos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <a href="{{ route($rutaVolver) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Volver al listado</a>
        {{ $movimientos->links() }}
    </div>
@endsection
