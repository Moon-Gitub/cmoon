@extends('layouts.app')

@section('titulo', 'Proveedores')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Razón social o CUIT"
                   class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
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
                    <th class="px-4 py-3">Razón social</th>
                    <th class="px-4 py-3">CUIT</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3 text-right">Ret. IIBB %</th>
                    <th class="px-4 py-3">Estado</th>
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
