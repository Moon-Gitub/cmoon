@extends('layouts.app')

@section('titulo', 'Usuarios')

@section('contenido')
    <div class="mb-4 flex flex-wrap items-center justify-between gap-3">
        <form method="GET" class="flex items-center gap-2">
            <input type="text" name="buscar" value="{{ request('buscar') }}" placeholder="Buscar por nombre, usuario o email"
                   class="w-72 rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
            <button class="rounded-lg border border-slate-300 bg-white px-3 py-2 text-sm hover:bg-slate-50">Buscar</button>
        </form>

        @can('usuarios.crear')
            <a href="{{ route('usuarios.create') }}"
               class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white hover:bg-indigo-700">
                + Nuevo usuario
            </a>
        @endcan
    </div>

    <div class="overflow-hidden rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Nombre</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Email</th>
                    <th class="px-4 py-3">Rol</th>
                    <th class="px-4 py-3">Sucursal</th>
                    <th class="px-4 py-3">Estado</th>
                    <th class="px-4 py-3">Último acceso</th>
                    <th class="px-4 py-3 text-right">Acciones</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($usuarios as $u)
                    <tr class="hover:bg-slate-50">
                        <td class="px-4 py-3 font-medium">{{ $u->name }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $u->usuario }}</td>
                        <td class="px-4 py-3 text-slate-600">{{ $u->email }}</td>
                        <td class="px-4 py-3">
                            <span class="rounded-full bg-indigo-50 px-2 py-0.5 text-xs font-medium text-indigo-700">
                                {{ $u->getRoleNames()->first() ?? '—' }}
                            </span>
                        </td>
                        <td class="px-4 py-3 text-slate-600">{{ $u->sucursal?->nombre ?? '—' }}</td>
                        <td class="px-4 py-3">
                            @if ($u->activo)
                                <span class="rounded-full bg-emerald-50 px-2 py-0.5 text-xs font-medium text-emerald-700">Activo</span>
                            @else
                                <span class="rounded-full bg-slate-100 px-2 py-0.5 text-xs font-medium text-slate-500">Inactivo</span>
                            @endif
                        </td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $u->ultimo_acceso_at?->diffForHumans() ?? 'Nunca' }}</td>
                        <td class="px-4 py-3 text-right">
                            <div class="flex items-center justify-end gap-2">
                                @can('usuarios.editar')
                                    <a href="{{ route('usuarios.edit', $u) }}"
                                       class="rounded-lg border border-slate-300 px-2.5 py-1 text-xs hover:bg-slate-100">Editar</a>
                                @endcan
                                @can('usuarios.eliminar')
                                    @if ($u->id !== auth()->id())
                                        <form method="POST" action="{{ route('usuarios.destroy', $u) }}"
                                              onsubmit="return confirm('¿Eliminar el usuario {{ $u->name }}?')">
                                            @csrf @method('DELETE')
                                            <button class="rounded-lg border border-red-200 px-2.5 py-1 text-xs text-red-600 hover:bg-red-50">Eliminar</button>
                                        </form>
                                    @endif
                                @endcan
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr><td colspan="8" class="px-4 py-8 text-center text-slate-400">No se encontraron usuarios.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $usuarios->links() }}</div>
@endsection
