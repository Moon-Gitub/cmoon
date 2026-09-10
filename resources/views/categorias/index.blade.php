@extends('layouts.app')

@section('titulo', 'Categorías')

@section('contenido')
    @can('categorias.gestionar')
        <form method="POST" action="{{ route('categorias.store') }}"
              class="mb-4 flex flex-wrap items-end gap-3 rounded-xl border border-slate-200 bg-white p-4 shadow-sm">
            @csrf
            <div class="min-w-[220px] flex-1">
                <label class="mb-1 block text-xs font-semibold uppercase tracking-wider text-slate-500">Nueva categoría</label>
                <input type="text" name="nombre" value="{{ old('nombre') }}" placeholder="Nombre de la categoría" required
                       class="w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
                @error('nombre')<p class="mt-1 text-xs text-red-600">{{ $message }}</p>@enderror
            </div>
            <button class="h-[38px] rounded-lg bg-indigo-600 px-5 text-sm font-semibold text-white hover:bg-indigo-700">
                Crear categoría
            </button>
        </form>
    @endcan

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full min-w-[720px] text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <x-sortable-th column="nombre" label="Nombre" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                    <x-sortable-th column="productos" label="Productos" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="desc" />
                    <x-sortable-th column="estado" label="Estado" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($categorias as $cat)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-2.5">
                            @can('categorias.gestionar')
                                <form method="POST" action="{{ route('categorias.update', $cat) }}" class="flex items-center gap-2">
                                    @csrf @method('PUT')
                                    <input type="text" name="nombre" value="{{ $cat->nombre }}"
                                           class="w-full min-w-[160px] max-w-md rounded-lg border border-transparent px-2 py-1 text-sm hover:border-slate-300 focus:border-indigo-500 focus:outline-none">
                                    <input type="hidden" name="activa" value="{{ $cat->activa ? 1 : 0 }}">
                                    <button class="shrink-0 rounded-lg border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100" title="Guardar nombre">✓</button>
                                </form>
                            @else
                                {{ $cat->nombre }}
                            @endcan
                        </td>
                        <td class="px-4 py-2.5 text-slate-600">{{ $cat->productos_count }}</td>
                        <td class="px-4 py-2.5">
                            @if ($cat->activa)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activa</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactiva</span>
                            @endif
                        </td>
                        <td class="px-3 py-2.5 text-right">
                            @php
                                $urlPdf = route('categorias.pdf', $cat);
                                $urlPublica = route('catalogo.categoria.publico', [
                                    'token' => $empresa->catalogo_share_token,
                                    'categoria' => $cat->id,
                                ]);
                                $wa = 'https://wa.me/?text='.rawurlencode('Lista de precios — '.$cat->nombre."\n".$urlPublica);
                            @endphp
                            <div class="inline-flex flex-nowrap items-center justify-end gap-1.5">
                                <a href="{{ $urlPdf }}" target="_blank" rel="noopener"
                                   class="whitespace-nowrap rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100"
                                   title="Abrir PDF">PDF</a>
                                <button type="button"
                                        data-url="{{ $urlPublica }}"
                                        onclick="navigator.clipboard.writeText(this.dataset.url); this.textContent='¡OK!'; setTimeout(() => this.textContent='Link', 1500)"
                                        class="whitespace-nowrap rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100"
                                        title="Copiar link público">Link</button>
                                <a href="{{ $wa }}" target="_blank" rel="noopener"
                                   class="whitespace-nowrap rounded-md border border-emerald-200 px-2 py-1 text-xs text-emerald-700 hover:bg-emerald-50"
                                   title="Compartir por WhatsApp">WA</a>
                                @can('categorias.gestionar')
                                    <form method="POST" action="{{ route('categorias.update', $cat) }" class="inline">
                                        @csrf @method('PUT')
                                        <input type="hidden" name="nombre" value="{{ $cat->nombre }}">
                                        <input type="hidden" name="activa" value="{{ $cat->activa ? 0 : 1 }}">
                                        <button class="whitespace-nowrap rounded-md border border-slate-300 px-2 py-1 text-xs hover:bg-slate-100">
                                            {{ $cat->activa ? 'Desactivar' : 'Activar' }}
                                        </button>
                                    </form>
                                    <form method="POST" action="{{ route('categorias.destroy', $cat) }}" class="inline"
                                          onsubmit="return confirm('¿Eliminar la categoría {{ $cat->nombre }}?')">
                                        @csrf @method('DELETE')
                                        <button class="whitespace-nowrap rounded-md border border-red-200 px-2 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="4" class="px-4 py-8 text-center text-slate-400">No hay categorías.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
@endsection
