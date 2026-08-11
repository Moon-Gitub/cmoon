@extends('layouts.app')

@section('titulo', "Auditoría: {$producto->nombre}")

@section('contenido')
    <div class="mb-4 flex items-center justify-between">
        <p class="text-sm text-slate-600">Historial de cambios de nombre, precios y código.</p>
        <a href="{{ route('productos.edit', $producto) }}" class="text-sm text-indigo-600 hover:text-indigo-800">← Volver al producto</a>
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs font-semibold uppercase tracking-wider text-slate-500">
                <tr>
                    <th class="px-4 py-3">Fecha</th>
                    <th class="px-4 py-3">Usuario</th>
                    <th class="px-4 py-3">Campo</th>
                    <th class="px-4 py-3">Anterior</th>
                    <th class="px-4 py-3">Nuevo</th>
                    <th class="px-4 py-3">Origen</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @forelse ($historial as $row)
                    <tr>
                        <td class="px-4 py-3 whitespace-nowrap text-slate-600">{{ $row->created_at?->format('d/m/Y H:i') }}</td>
                        <td class="px-4 py-3">{{ $row->usuario?->name ?? 'Sistema' }}</td>
                        <td class="px-4 py-3 font-mono text-xs">{{ $row->campo }}</td>
                        <td class="px-4 py-3 text-slate-500">{{ $row->valor_anterior }}</td>
                        <td class="px-4 py-3 font-medium">{{ $row->valor_nuevo }}</td>
                        <td class="px-4 py-3 text-xs text-slate-500">{{ $row->origen }}</td>
                    </tr>
                @empty
                    <tr><td colspan="6" class="px-4 py-8 text-center text-slate-400">Sin cambios registrados aún.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>

    <div class="mt-4">{{ $historial->links() }}</div>
@endsection
