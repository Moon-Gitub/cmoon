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
            @if (! $esCliente && ($agenteRetencion ?? false))
                <a href="{{ route('retenciones.index', ['proveedor_id' => $titular->id]) }}"
                   class="mt-3 inline-block text-sm font-medium text-indigo-600 hover:text-indigo-800">
                    Ver retenciones IIBB →
                </a>
            @endif
        </div>

        @can('cuentas.registrar')
            @if ($esCliente)
                @include('cuentas.partials.form-cliente')
            @else
                @include('cuentas.partials.form-proveedor')
            @endif
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Tipo</th>
                    <th class="px-4 py-3">Concepto</th>
                    @unless ($esCliente)
                        <th class="px-4 py-3">Factura</th>
                    @endunless
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
                        <td class="px-4 py-3">
                            {{ $mov->concepto }}
                            @if ($mov->medioPago)
                                <span class="block text-xs text-slate-400">{{ $mov->medioPago->nombre }}</span>
                            @endif
                        </td>
                        @unless ($esCliente)
                            <td class="px-4 py-3 font-mono text-xs text-slate-500">{{ $mov->factura_numero ?? '—' }}</td>
                        @endunless
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $mov->usuario?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 text-right font-semibold {{ (float) $mov->importe >= 0 ? 'text-red-600' : 'text-emerald-600' }}">
                            {{ (float) $mov->importe >= 0 ? '+' : '−' }} $ {{ number_format(abs((float) $mov->importe), 2, ',', '.') }}
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="{{ $esCliente ? 5 : 6 }}" class="px-4 py-8 text-center text-slate-400">Sin movimientos registrados.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4 flex items-center justify-between">
        <a href="{{ route($rutaVolver) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Volver al listado</a>
        {{ $movimientos->links() }}
    </div>
@endsection
