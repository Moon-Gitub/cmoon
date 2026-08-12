@extends('layouts.app')

@section('titulo', 'Publicar productos en canales')

@section('contenido')
    <p class="mb-4 text-sm text-slate-600">
        Elegí qué productos van a <strong>Shopify</strong>, <strong>WhatsApp</strong> o <strong>Tiendanube</strong>.
        El bot de WhatsApp solo habla de los tildados en WhatsApp. El push a Shopify/Tiendanube solo lleva esos.
    </p>

    @if (session('ok'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('ok') }}</div>
    @endif

    <form method="GET" class="mb-4 flex flex-wrap items-center gap-2">
        <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre o código"
               class="w-64 rounded-lg border border-slate-300 px-3 py-2 text-sm">
        <select name="categoria" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Todas las categorías</option>
            @foreach ($categorias as $cat)
                <option value="{{ $cat->id }}" {{ request('categoria') == $cat->id ? 'selected' : '' }}>{{ $cat->nombre }}</option>
            @endforeach
        </select>
        <select name="canal" class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
            <option value="">Cualquier canal</option>
            <option value="shopify" {{ request('canal') === 'shopify' ? 'selected' : '' }}>En Shopify</option>
            <option value="whatsapp" {{ request('canal') === 'whatsapp' ? 'selected' : '' }}>En WhatsApp</option>
            <option value="tiendanube" {{ request('canal') === 'tiendanube' ? 'selected' : '' }}>En Tiendanube</option>
        </select>
        <label class="flex items-center gap-1 text-sm text-slate-600">
            <input type="checkbox" name="sin_canal" value="1" {{ request('sin_canal') === '1' ? 'checked' : '' }}>
            Sin canal
        </label>
        <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Filtrar</button>
    </form>

    <form method="POST" action="{{ route('productos.canales.aplicar') }}"
          x-data="{ all: false, toggle() { this.all = !this.all; $el.querySelectorAll('[name=\'ids[]\']').forEach(c => c.checked = this.all) } }">
        @csrf
        <div class="mb-3 flex flex-wrap items-end gap-2 rounded-lg border border-slate-200 bg-slate-50 p-3">
            <button type="button" @click="toggle()" class="rounded border border-slate-300 bg-white px-3 py-2 text-xs">Seleccionar página</button>
            <div>
                <label class="mb-1 block text-xs text-slate-500">Canal</label>
                <select name="canal" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="whatsapp">WhatsApp</option>
                    <option value="shopify">Shopify</option>
                    <option value="tiendanube">Tiendanube</option>
                </select>
            </div>
            <div>
                <label class="mb-1 block text-xs text-slate-500">Acción</label>
                <select name="accion" required class="rounded-lg border border-slate-300 px-3 py-2 text-sm">
                    <option value="activar">Publicar</option>
                    <option value="desactivar">Quitar</option>
                </select>
            </div>
            <button class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                Aplicar a seleccionados
            </button>
            <button type="button" id="btn-canales-ia"
                    class="rounded-lg border border-slate-300 bg-white px-4 py-2 text-sm">
                Sugerir publicación (gratis)
            </button>
        </div>

        <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
            <table class="w-full text-sm">
                <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                    <tr>
                        <th class="px-3 py-3 w-10"></th>
                        <th class="px-3 py-3">Producto</th>
                        <th class="px-3 py-3">Categoría</th>
                        <th class="px-3 py-3 text-right">Precio</th>
                        <th class="px-3 py-3">Canales</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-slate-100">
                    @forelse ($productos as $p)
                        <tr class="hover:bg-slate-50">
                            <td class="px-3 py-2">
                                <input type="checkbox" name="ids[]" value="{{ $p->id }}" class="rounded border-slate-300">
                            </td>
                            <td class="px-3 py-2">
                                <div class="font-medium">{{ $p->nombre }}</div>
                                <div class="font-mono text-xs text-slate-500">{{ $p->codigo }}</div>
                            </td>
                            <td class="px-3 py-2 text-slate-600">{{ $p->categoria?->nombre ?? '—' }}</td>
                            <td class="px-3 py-2 text-right">$ {{ number_format((float) $p->precio_venta, 2, ',', '.') }}</td>
                            <td class="px-3 py-2">
                                @if ($p->publicar_shopify)<span class="mr-1 rounded bg-lime-50 px-1.5 py-0.5 text-[10px] font-medium text-lime-700">SHOPIFY</span>@endif
                                @if ($p->publicar_whatsapp)<span class="mr-1 rounded bg-emerald-50 px-1.5 py-0.5 text-[10px] font-medium text-emerald-700">WA</span>@endif
                                @if ($p->publicar_tiendanube)<span class="mr-1 rounded bg-sky-50 px-1.5 py-0.5 text-[10px] font-medium text-sky-700">TN</span>@endif
                                @if (! $p->publicar_shopify && ! $p->publicar_whatsapp && ! $p->publicar_tiendanube)
                                    <span class="text-xs text-slate-400">ninguno</span>
                                @endif
                            </td>
                        </tr>
                    @empty
                        <tr><td colspan="5" class="px-4 py-8 text-center text-slate-400">No hay productos.</td></tr>
                    @endforelse
                </tbody>
            </table>
        </div>
    </form>

    <div class="mt-4">{{ $productos->links() }}</div>
    <script>
    document.getElementById('btn-canales-ia')?.addEventListener('click', async () => {
        const ids = [...document.querySelectorAll('input[name="ids[]"]:checked')].map(i => i.value);
        if (!ids.length) { alert('Seleccioná productos de esta página.'); return; }
        const res = await fetch(@json(route('ia.productos.canales.aplicar')), {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'Accept': 'application/json',
                'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]').content,
            },
            body: JSON.stringify({ ids }),
        });
        const data = await res.json();
        if (data.ok) location.reload();
        else alert(data.texto || 'No se pudo aplicar.');
    });
    </script>
@endsection
