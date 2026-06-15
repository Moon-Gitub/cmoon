@extends('layouts.app')

@section('titulo', 'Clientes')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Nombre, documento o email"
                   class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
        </form>

        @can('clientes.crear')
            <a href="{{ route('clientes.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + Nuevo cliente
            </a>
        @endcan
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Documento</th>
                    <th class="px-4 py-3">Cond. IVA</th>
                    <th class="px-4 py-3">Teléfono</th>
                    <th class="px-4 py-3">Lista de precios</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($clientes as $c)
                    <tr class="hover:bg-slate-50 {{ ! $c->activo ? 'opacity-60' : '' }}">
                        <td class="px-4 py-3 font-medium">{{ $c->nombre }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $c->tipo_documento }} {{ $c->documento ?? '—' }}</td>
                        <td class="px-4 py-3 text-xs text-slate-600">{{ str_replace('_', ' ', $c->condicion_iva) }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $c->telefono ?? '—' }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $c->listaPrecio?->nombre ?? 'General' }}</td>
                        <td class="px-4 py-3">
                            @if ($c->activo)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                <a href="{{ route('clientes.show', $c) }}"
                                   class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Ver</a>
                                @can('cuentas.ver')
                                    <a href="{{ route('clientes.cuenta', $c) }}"
                                       class="rounded-lg border border-indigo-200 px-2.5 py-1 text-xs text-indigo-700 hover:bg-indigo-50">Cta. cte.</a>
                                @endcan
                                @can('clientes.editar')
                                    <a href="{{ route('clientes.edit', $c) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Editar</a>
                                @endcan
                                @can('clientes.eliminar')
                                    <form method="POST" action="{{ route('clientes.destroy', $c) }}"
                                          onsubmit="return confirm('¿Eliminar a {{ $c->nombre }}?')">
                                        @csrf @method('DELETE')
                                        <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                    </form>
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="7" class="px-4 py-8 text-center text-slate-400">No se encontraron clientes.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $clientes->links() }}</div>
@endsection
