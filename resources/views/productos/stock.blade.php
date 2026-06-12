@extends('layouts.app')

@section('titulo', "Stock: {$producto->nombre}")

@section('contenido')
    <div class="grid grid-cols-1 gap-6 lg:grid-cols-2">

        <div class="space-y-4">
            <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                <h2 class="mb-4 text-base font-semibold">Stock por sucursal</h2>
                <table class="w-full text-sm">
                    <thead class="text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                        <tr>
                            <th class="py-2">Sucursal</th>
                            <th class="py-2 text-right">Cantidad actual</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100">
                        @foreach ($sucursales as $suc)
                            <tr>
                                <td class="py-2">{{ $suc->nombre }}</td>
                                <td class="py-2 text-right font-semibold">
                                    {{ rtrim(rtrim(number_format($producto->stockEn($suc->id), 3, ',', '.'), '0'), ',') }}
                                    <span class="text-xs font-normal text-slate-400">{{ $producto->unidad }}</span>
                                </td>
                            </tr>
                        @endforeach
                        <tr class="bg-slate-50">
                            <td class="py-2 font-semibold">Total</td>
                            <td class="py-2 text-right font-bold">
                                {{ rtrim(rtrim(number_format($producto->stockTotal(), 3, ',', '.'), '0'), ',') }}
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>

            <form method="POST" action="{{ route('productos.stock.ajustar', $producto) }}"
                  class="space-y-4 rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
                @csrf
                <h2 class="text-base font-semibold">Ajustar stock</h2>
                <p class="text-xs text-slate-500">Fija la cantidad final en la sucursal. La diferencia queda registrada en el historial.</p>

                <div class="grid grid-cols-1 gap-4 sm:grid-cols-2">
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Sucursal *</label>
                        <select name="sucursal_id" required
                                class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                            @foreach ($sucursales as $suc)
                                <option value="{{ $suc->id }}">{{ $suc->nombre }}</option>
                            @endforeach
                        </select>
                    </div>
                    <div>
                        <label class="mb-1 block text-sm font-medium text-slate-700">Cantidad final *</label>
                        <input type="number" step="0.001" min="0" name="cantidad" required
                               class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                        @error('cantidad')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
                    </div>
                </div>

                <div>
                    <label class="mb-1 block text-sm font-medium text-slate-700">Observación</label>
                    <input type="text" name="observacion" placeholder="Ej: recuento físico"
                           class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                </div>

                <div class="flex items-center gap-3">
                    <button type="submit"
                            class="rounded-lg bg-indigo-600 px-5 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                        Aplicar ajuste
                    </button>
                    <a href="{{ route('productos.index') }}" class="text-sm text-slate-500 hover:text-slate-700">Volver a productos</a>
                </div>
            </form>
        </div>

        <div class="rounded-xl border border-slate-200 bg-white p-6 shadow-sm">
            <h2 class="mb-4 text-base font-semibold">Últimos movimientos</h2>
            <div class="max-h-[600px] space-y-2 overflow-y-auto">
                @forelse ($movimientos as $mov)
                    <div class="flex items-center justify-between rounded-lg border border-slate-100 px-3 py-2 text-sm">
                        <div>
                            <p class="font-medium capitalize">{{ $mov->tipo }} · {{ $mov->sucursal->nombre }}</p>
                            <p class="text-xs text-slate-500">
                                {{ $mov->created_at->format('d/m/Y H:i') }}
                                · {{ $mov->usuario?->name ?? 'Sistema' }}
                                @if ($mov->observacion) · {{ $mov->observacion }} @endif
                            </p>
                        </div>
                        <div class="text-right">
                            <p class="font-semibold {{ (float) $mov->cantidad >= 0 ? 'text-emerald-600' : 'text-red-600' }}">
                                {{ (float) $mov->cantidad >= 0 ? '+' : '' }}{{ rtrim(rtrim(number_format((float) $mov->cantidad, 3, ',', '.'), '0'), ',') }}
                            </p>
                            <p class="text-xs text-slate-400">Quedó: {{ rtrim(rtrim(number_format((float) $mov->stock_resultante, 3, ',', '.'), '0'), ',') }}</p>
                        </div>
                    </div>
                @empty
                    <p class="py-6 text-center text-sm text-slate-400">Sin movimientos registrados.</p>
                @endforelse
            </div>
        </div>
    </div>
@endsection
