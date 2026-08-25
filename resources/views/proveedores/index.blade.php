@extends('layouts.app')

@section('titulo', 'Proveedores')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-start justify-between gap-3 pb-3">
        <form method="GET" class="flex flex-wrap items-center gap-2">
                <x-sort-hidden :sort="$sort ?? null" :dir="$dir ?? null" />
            <x-filtro-busqueda
                :url="route('busqueda.proveedores')"
                name="buscar"
                placeholder="Razón social o CUIT…"
                :value="request('buscar')"
                :navigate="false"
            />
            <button type="submit" class="h-[38px] rounded-lg border border-slate-300 bg-white px-3 text-sm hover:bg-slate-50">Buscar</button>
        </form>

        @can('proveedores.crear')
            <a href="{{ route('proveedores.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + Nuevo proveedor
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                        <x-sortable-th column="razon_social" label="Razón social" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="cuit" label="CUIT" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="telefono" label="Teléfono" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="email" label="Email" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <x-sortable-th column="retencion" label="Ret. IIBB %" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" align="right" />
                        <x-sortable-th column="estado" label="Estado" :sort="$sort ?? 'id'" :dir="$dir ?? 'asc'" default-dir="asc" />
                        <th class="px-4 py-3 text-right">Acciones</th>
                    </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($proveedores as $p)
                    <tr class="hover:bg-slate-50 {{ ! $p->activo ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $p->razon_social }}</td>
                        <td class="px-4 py-3 font-mono text-xs text-slate-600">{{ $p->cuit ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->telefono ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $p->email ?? '—' }}</td>
                        <td class="px-4 py-3 text-right text-slate-600">
                            {{ (float) $p->alicuota_retencion_iibb > 0 ? rtrim(rtrim(number_format((float) $p->alicuota_retencion_iibb, 2, ',', '.'), '0'), ',') : '—' }}
                        </td>
                        <td class="px-4 py-3">
                            @if ($p->activo)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('cuentas.ver')
                                    <a href="{{ route('proveedores.cuenta', $p) }}"
                                       class="rounded-lg border border-indigo-200 px-2.5 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Cta. cte.</a>
                                @endcan
                                @can('proveedores.editar')
                                    <a href="{{ route('proveedores.edit', $p) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Editar</a>
                                @endcan
                                @can('proveedores.eliminar')
                                    <form method="POST" action="{{ route('proveedores.destroy', $p) }}"
                                          onsubmit="return confirm('¿Eliminar a {{ $p->razon_social }}?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No se encontraron proveedores.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $proveedores->links() }}</div>
@endsection
