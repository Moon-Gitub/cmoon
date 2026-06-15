@extends('layouts.app')

@section('titulo', $cliente->nombre)

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-3">
        <div class="lg:col-span-2 space-y-6">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Ventas recientes</h2>
                <div class="divide-y divide-slate-100 text-sm">
                    @forelse ($ventas as $v)
                        <div class="flex justify-between py-2">
                            <a href="{{ route('ventas.show', $v) }}" class="font-medium text-indigo-600 hover:underline">
                                #{{ str_pad($v->numero, 6, '0', STR_PAD_LEFT) }} · {{ $v->fecha->format('d/m/Y') }}
                            </a>
                            <span class="font-semibold">$ {{ number_format((float) $v->total, 2, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-slate-400">Sin ventas registradas.</p>
                    @endforelse
                </div>
            </div>
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Presupuestos / pedidos</h2>
                <div class="divide-y divide-slate-100 text-sm">
                    @forelse ($presupuestos as $p)
                        <div class="flex justify-between py-2">
                            <a href="{{ route('presupuestos.show', $p) }}" class="font-medium text-indigo-600 hover:underline">
                                #{{ str_pad($p->numero, 6, '0', STR_PAD_LEFT) }} · {{ $p->estado }}
                            </a>
                            <span class="font-semibold">$ {{ number_format((float) $p->total, 2, ',', '.') }}</span>
                        </div>
                    @empty
                        <p class="text-slate-400">Sin presupuestos.</p>
                    @endforelse
                </div>
            </div>
        </div>
        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-5 shadow-sm">
                <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Ficha</h2>
                <dl class="space-y-2 text-sm">
                    <div class="flex justify-between"><dt class="text-slate-500">Documento</dt><dd>{{ $cliente->tipo_documento }} {{ $cliente->documento ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Teléfono</dt><dd>{{ $cliente->telefono ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Domicilio</dt><dd class="text-right">{{ $cliente->domicilio ?? '—' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Lista precios</dt><dd>{{ $cliente->listaPrecio?->nombre ?? 'General' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Vendedor</dt><dd>{{ $cliente->vendedor?->name ?? 'Sin asignar' }}</dd></div>
                    <div class="flex justify-between"><dt class="text-slate-500">Límite crédito</dt><dd>{{ $cliente->limite_credito ? '$ '.number_format((float) $cliente->limite_credito, 2, ',', '.') : '—' }}</dd></div>
                    <div class="flex justify-between border-t border-slate-100 pt-2"><dt class="text-slate-500">Saldo cta. cte.</dt><dd class="font-bold {{ $saldo > 0 ? 'text-red-600' : 'text-emerald-600' }}">$ {{ number_format($saldo, 2, ',', '.') }}</dd></div>
                </dl>
            </div>
            @can('cuentas.ver')
                <a href="{{ route('clientes.cuenta', $cliente) }}" class="block w-full rounded-xl bg-indigo-600 py-3 text-center text-sm font-bold text-white hover:bg-indigo-700">Ver cuenta corriente</a>
            @endcan
            @can('clientes.editar')
                <a href="{{ route('clientes.edit', $cliente) }}" class="block w-full rounded-xl border border-slate-300 py-2.5 text-center text-sm font-medium hover:bg-slate-50">Editar cliente</a>
            @endcan
            <a href="{{ route('clientes.index') }}" class="block text-center text-sm text-indigo-600 hover:text-indigo-800">← Volver</a>
        </div>
    </div>
@endsection
