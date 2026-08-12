@extends('layouts.app')

@section('titulo', 'Chats WhatsApp')

@section('contenido')
    <div class="mb-4 flex items-center justify-between">
        <a href="{{ route('ycloud.index') }}" class="text-sm text-indigo-600">← WhatsApp</a>
        <span class="text-sm text-slate-500">{{ $conversaciones->filter(fn ($c) => $c->enHandoff())->count() }} en espera de humano</span>
    </div>

    @if(session('ok'))
        <div class="mb-4 rounded-lg border border-emerald-200 bg-emerald-50 px-4 py-3 text-emerald-800">{{ session('ok') }}</div>
    @endif

    <div class="mb-6 grid gap-3 md:grid-cols-2 lg:grid-cols-3">
        @foreach($conversaciones as $c)
            <div class="rounded-lg border border-slate-200 bg-white p-3 text-sm">
                <div class="font-medium">{{ $c->nombre ?: $c->telefono }}</div>
                <div class="font-mono text-xs text-slate-500">{{ $c->telefono }}</div>
                @if($c->enHandoff())
                    <form method="POST" action="{{ route('ycloud.reanudar', $c) }}" class="mt-2">
                        @csrf
                        <button class="rounded border border-amber-300 bg-amber-50 px-2 py-1 text-xs text-amber-800">Reanudar bot</button>
                    </form>
                @endif
            </div>
        @endforeach
    </div>

    <div class="overflow-x-auto rounded-xl border border-slate-200 bg-white">
        <table class="w-full text-sm">
            <thead class="bg-slate-50 text-left text-xs uppercase text-slate-500">
                <tr>
                    <th class="px-3 py-2">Fecha</th>
                    <th class="px-3 py-2">Dir</th>
                    <th class="px-3 py-2">De / a</th>
                    <th class="px-3 py-2">Texto</th>
                </tr>
            </thead>
            <tbody class="divide-y divide-slate-100">
                @foreach($mensajes as $m)
                    <tr>
                        <td class="whitespace-nowrap px-3 py-2 text-xs text-slate-500">{{ $m->created_at?->format('d/m H:i') }}</td>
                        <td class="px-3 py-2">{{ $m->direccion }}</td>
                        <td class="px-3 py-2 font-mono text-xs">{{ $m->from_phone }} → {{ $m->to_phone }}</td>
                        <td class="px-3 py-2">{{ \Illuminate\Support\Str::limit($m->body ?: $m->respuesta, 160) }}</td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>
    <div class="mt-4">{{ $mensajes->links() }}</div>
@endsection
