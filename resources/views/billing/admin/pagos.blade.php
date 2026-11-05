@extends('billing.admin.layout')
@section('titulo', 'Pagos')
@php use App\Billing\Support\Money; @endphp

@section('contenido')
    <div class="overflow-x-auto rounded-xl bg-white shadow-sm">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr><th class="px-4 py-2">Fecha</th><th>Cliente</th><th>Medio</th><th>Operación</th><th>Estado</th><th>Acreditado</th><th class="px-4 text-right">Importe</th></tr>
            </thead>
            @foreach ($pagos as $p)
                <tr class="border-t border-slate-100">
                    <td class="px-4 py-2 whitespace-nowrap">{{ ($p->aprobado_at ?? $p->created_at)->format('d/m/Y H:i') }}</td>
                    <td>{{ $p->customer?->nombre ?? '—' }}</td>
                    <td>{{ $p->proveedor }} {{ $p->metodo && $p->metodo !== $p->proveedor ? '· '.$p->metodo : '' }}</td>
                    <td class="font-mono text-xs">{{ \Illuminate\Support\Str::limit($p->external_id, 20) }}</td>
                    <td>{{ $p->estado }}</td>
                    <td>{{ $p->acreditado_at ? '✅' : '—' }}</td>
                    <td class="px-4 text-right font-medium">{{ Money::formato($p->importe_centavos) }}</td>
                </tr>
            @endforeach
        </table>
    </div>
    <div class="mt-4">{{ $pagos->links() }}</div>
@endsection
