@extends('layouts.app')

@section('titulo', 'Facturas recurrentes')

@section('contenido')
    <div class="mb-6">
        <h1 class="text-xl font-bold text-slate-800">Facturas recurrentes</h1>
        <p class="mt-1 text-sm text-slate-500">
            Recordatorios mensuales por email según día del mes. El job diario envía avisos a clientes con email cargado.
        </p>
    </div>

    <div class="mb-6 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
        <h2 class="mb-3 text-sm font-semibold uppercase tracking-wider text-slate-500">Nueva recurrente</h2>
        <form method="POST" action="{{ route('facturas-recurrentes.store') }}" class="grid grid-cols-1 gap-3 md:grid-cols-2 lg:grid-cols-3">
            @csrf
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Cliente</label>
                <select name="cliente_id" required class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">Seleccionar…</option>
                    @foreach ($clientes as $cliente)
                        <option value="{{ $cliente->id }}" @selected(old('cliente_id') == $cliente->id)>
                            {{ $cliente->nombre }}@if ($cliente->email) ({{ $cliente->email }})@endif
                        </option>
                    @endforeach
                </select>
                @error('cliente_id')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Día del mes</label>
                <input type="number" name="dia_mes" min="1" max="31" value="{{ old('dia_mes', 1) }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('dia_mes')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Monto</label>
                <input type="number" name="monto" step="0.01" min="0.01" value="{{ old('monto') }}" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                @error('monto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div class="md:col-span-2">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Concepto</label>
                <input type="text" name="concepto" value="{{ old('concepto') }}" required maxlength="255"
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm"
                       placeholder="Ej. Abono mensual">
                @error('concepto')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <div>
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Emisor (opcional)</label>
                <select name="emisor_id" class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="">—</option>
                    @foreach ($emisores as $emisor)
                        <option value="{{ $emisor->id }}" @selected(old('emisor_id') == $emisor->id)>
                            {{ $emisor->razon_social }} ({{ $emisor->cuit }})
                        </option>
                    @endforeach
                </select>
            </div>
            <div class="flex items-end md:col-span-2 lg:col-span-3">
                <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Guardar
                </button>
            </div>
        </form>
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Cliente</th>
                    <th class="px-4 py-3">Concepto</th>
                    <th class="px-4 py-3">Día</th>
                    <th class="px-4 py-3">Próximo</th>
                    <th class="px-4 py-3 text-right">Monto</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Último envío</th>
                    <th class="px-4 py-3">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($facturas as $fr)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3">
                            <div class="font-medium">{{ $fr->cliente?->nombre ?? '—' }}</div>
                            <div class="text-xs text-slate-400">{{ $fr->cliente?->email ?: 'Sin email' }}</div>
                        </td>
                        <td class="px-4 py-3">
                            {{ $fr->concepto }}
                            @if ($fr->emisor)
                                <div class="text-xs text-slate-400">{{ $fr->emisor->razon_social }}</div>
                            @endif
                        </td>
                        <td class="px-4 py-3">{{ $fr->dia_mes }}</td>
                        <td class="px-4 py-3">{{ $fr->proximo_vencimiento?->format('d/m/Y') }}</td>
                        <td class="px-4 py-3 text-right font-semibold">$ {{ number_format((float) $fr->monto, 2, ',', '.') }}</td>
                        <td class="px-4 py-3">
                            @if ($fr->activa)
                                <span class="rounded-full bg-emerald-100 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Pausada</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">
                            {{ $fr->ultimo_envio_at?->format('d/m/Y H:i') ?? '—' }}
                        </td>
                        <td class="px-4 py-3">
                            <div class="flex flex-wrap gap-1">
                                <form method="POST" action="{{ route('facturas-recurrentes.toggle', $fr) }}">
                                    @csrf
                                    <button class="rounded border border-slate-300 bg-white px-2 py-1 text-xs hover:bg-slate-50">
                                        {{ $fr->activa ? 'Pausar' : 'Activar' }}
                                    </button>
                                </form>
                                <form method="POST" action="{{ route('facturas-recurrentes.destroy', $fr) }}"
                                      onsubmit="return confirm('¿Eliminar esta factura recurrente?')">
                                    @csrf @method('DELETE')
                                    <button class="rounded border border-red-200 bg-white px-2 py-1 text-xs text-red-600 hover:bg-red-50">
                                        Eliminar
                                    </button>
                                </form>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr>
                        <td colspan="8" class="px-4 py-10 text-center text-slate-400">No hay facturas recurrentes.</td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $facturas->links() }}</div>
@endsection
