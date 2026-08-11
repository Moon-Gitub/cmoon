@extends('layouts.app')

@section('titulo', 'Logs Shopify')

@section('contenido')
<div class="space-y-4">
    <div class="flex items-center justify-between">
        <h1 class="text-xl font-bold">Logs Shopify — {{ $integracion->store_name ?: $integracion->store_domain }}</h1>
        <a href="{{ route('shopify.index') }}" class="text-sm text-indigo-600 hover:underline">← Volver</a>
    </div>

    <div class="rounded-xl border border-slate-200 bg-white overflow-hidden">
        <table class="min-w-full text-sm">
            <thead class="bg-slate-50 text-left text-slate-500">
                <tr>
                    <th class="px-4 py-2">Fecha</th>
                    <th class="px-4 py-2">Tipo</th>
                    <th class="px-4 py-2">Dir</th>
                    <th class="px-4 py-2">Status</th>
                    <th class="px-4 py-2">Mensaje</th>
                </tr>
            </thead>
            <tbody>
                @forelse($logs as $log)
                    <tr class="border-t">
                        <td class="px-4 py-2 whitespace-nowrap">{{ $log->created_at }}</td>
                        <td class="px-4 py-2">{{ $log->tipo }}</td>
                        <td class="px-4 py-2">{{ $log->direccion }}</td>
                        <td class="px-4 py-2 {{ $log->status === 'ok' ? 'text-emerald-600' : 'text-red-600' }}">{{ $log->status }}</td>
                        <td class="px-4 py-2">{{ $log->mensaje }}</td>
                    </tr>
                @empty
                    <tr><td colspan="5" class="px-4 py-6 text-center text-slate-400">Sin logs</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $logs->links() }}
</div>
@endsection
