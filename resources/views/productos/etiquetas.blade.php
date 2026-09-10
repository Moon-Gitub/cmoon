@extends('layouts.app')

@section('titulo', 'Imprimir etiquetas')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-600">
                Armá la lista y generá PDF de góndola, oferta, QR, código de barras o combos.
                Tamaño compatible con el sistema anterior (mejorado).
            </p>
        </div>
        <a href="{{ route('productos.index') }}" class="text-sm text-indigo-600 hover:underline">← Volver a productos</a>
    </div>

    <div class="grid grid-cols-1 gap-4 lg:grid-cols-2">
        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">Buscar y agregar</h2>
            <form method="GET" class="flex flex-wrap gap-2">
                <input type="text" name="buscar" value="{{ $buscar }}" placeholder="Código o nombre…"
                       class="min-w-[180px] flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <label class="inline-flex items-center gap-2 rounded-lg border border-slate-200 px-3 py-2 text-sm text-slate-600">
                    <input type="checkbox" name="solo_combos" value="1" @checked($soloCombos) class="rounded border-slate-300">
                    Solo combos
                </label>
                <button class="rounded-lg bg-slate-800 px-4 py-2 text-sm font-semibold text-white hover:bg-slate-900">Buscar</button>
            </form>

            <form method="POST" action="{{ route('productos.etiquetas.agregar') }}">
                @csrf
                <div class="max-h-96 overflow-y-auto rounded-lg border border-slate-100">
                    <table class="w-full text-sm">
                        <thead class="sticky top-0 bg-slate-50 text-left text-xs uppercase text-slate-500">
                            <tr>
                                <th class="px-3 py-2 w-8"></th>
                                <th class="px-3 py-2">Producto</th>
                                <th class="px-3 py-2 text-right">Precio</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-100">
                            @forelse ($candidatos as $p)
                                <tr class="hover:bg-slate-50">
                                    <td class="px-3 py-2">
                                        <input type="checkbox" name="producto_ids[]" value="{{ $p->id }}"
                                               class="rounded border-slate-300">
                                    </td>
                                    <td class="px-3 py-2">
                                        <span class="font-medium">{{ $p->nombre }}</span>
                                        <span class="block text-xs text-slate-400">{{ $p->codigo }}
                                            @if ($p->es_combo)<span class="ml-1 text-purple-600">COMBO</span>@endif
                                            @if ($p->promoActiva())<span class="ml-1 text-red-600">OFERTA</span>@endif
                                        </span>
                                    </td>
                                    <td class="px-3 py-2 text-right font-semibold">
                                        $ {{ number_format($p->precioGondola(), 2, ',', '.') }}
                                    </td>
                                </tr>
                            @empty
                                <tr><td colspan="3" class="px-3 py-8 text-center text-slate-400">Sin resultados.</td></tr>
                            @endforelse
                        </tbody>
                    </table>
                </div>
                <button class="mt-3 w-full rounded-lg bg-indigo-600 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                    Agregar seleccionados
                </button>
            </form>
        </div>

        <div class="space-y-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            <div class="flex items-center justify-between">
                <h2 class="text-sm font-semibold uppercase tracking-wider text-slate-500">
                    Lista a imprimir ({{ $seleccion->count() }})
                </h2>
                @if ($seleccion->isNotEmpty())
                    <form method="POST" action="{{ route('productos.etiquetas.limpiar') }}"
                          onsubmit="return confirm('¿Vaciar la lista?')">
                        @csrf
                        <button class="text-xs font-medium text-red-500 hover:text-red-700">Limpiar</button>
                    </form>
                @endif
            </div>

            <div class="max-h-72 overflow-y-auto rounded-lg border border-slate-100">
                <table class="w-full text-sm">
                    <tbody class="divide-y divide-slate-100">
                        @forelse ($seleccion as $p)
                            <tr>
                                <td class="px-3 py-2">
                                    <span class="font-medium">{{ $p->nombre }}</span>
                                    <span class="block text-xs text-slate-400">{{ $p->codigo }}
                                        @if ($p->es_combo)<span class="ml-1 text-purple-600">COMBO</span>@endif
                                    </span>
                                </td>
                                <td class="px-3 py-2 text-right font-semibold whitespace-nowrap">
                                    $ {{ number_format($p->precioGondola(), 2, ',', '.') }}
                                </td>
                                <td class="px-2 py-2 text-right">
                                    <form method="POST" action="{{ route('productos.etiquetas.quitar') }}">
                                        @csrf
                                        <input type="hidden" name="producto_id" value="{{ $p->id }}">
                                        <button class="text-xs text-slate-400 hover:text-red-600">Quitar</button>
                                    </form>
                                </td>
                            </tr>
                        @empty
                            <tr><td class="px-3 py-10 text-center text-slate-400">Todavía no agregaste productos.</td></tr>
                        @endforelse
                    </tbody>
                </table>
            </div>

            <div class="grid grid-cols-1 gap-2 sm:grid-cols-2">
                @foreach ([
                    'normal' => ['Góndola (3×8)', 'Precio de góndola en hoja A4'],
                    'oferta' => ['Oferta (1/hoja)', 'Etiqueta grande tipo oferta'],
                    'qr' => ['Códigos QR', 'Escanea y ve el precio'],
                    'barcode' => ['Códigos de barras', 'Code 128 del código'],
                    'combo' => ['Combos', 'Precio + qué incluye'],
                ] as $tipo => [$titulo, $desc])
                    <a href="{{ $seleccion->isEmpty() ? '#' : route('productos.etiquetas.pdf', ['tipo' => $tipo]) }}"
                       target="_blank"
                       @class([
                           'rounded-xl border px-4 py-3 transition',
                           'pointer-events-none opacity-40 border-slate-200' => $seleccion->isEmpty(),
                           'border-indigo-200 bg-indigo-50 hover:bg-indigo-100' => $seleccion->isNotEmpty() && $tipo === 'normal',
                           'border-red-200 bg-red-50 hover:bg-red-100' => $seleccion->isNotEmpty() && $tipo === 'oferta',
                           'border-emerald-200 bg-emerald-50 hover:bg-emerald-100' => $seleccion->isNotEmpty() && $tipo === 'qr',
                           'border-slate-200 bg-slate-50 hover:bg-slate-100' => $seleccion->isNotEmpty() && $tipo === 'barcode',
                           'border-purple-200 bg-purple-50 hover:bg-purple-100' => $seleccion->isNotEmpty() && $tipo === 'combo',
                       ])>
                        <p class="text-sm font-semibold text-slate-800">{{ $titulo }}</p>
                        <p class="text-xs text-slate-500">{{ $desc }}</p>
                    </a>
                @endforeach
            </div>
        </div>
    </div>
@endsection
