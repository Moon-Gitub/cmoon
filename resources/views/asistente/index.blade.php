@extends('layouts.app')

@section('titulo', 'Asistente IA')

@section('contenido')
<div class="mx-auto max-w-3xl space-y-4" x-data="asistenteChat()">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <p class="text-sm text-slate-600">
                Plan <strong>{{ $cupo['plan'] === 'abono' ? 'abono' : 'incluido' }}</strong>
                · {{ $cupo['usados'] }} / {{ $cupo['cupo'] }} preguntas este mes
                · quedan <strong x-text="restantes">{{ $cupo['restantes'] }}</strong>
                @if($cupo['abono_hasta'])
                    · abono hasta {{ $cupo['abono_hasta'] }}
                @endif
            </p>
        </div>
        @if($cupo['plan'] !== 'abono')
            @if($cupo['solicitado'])
                <span class="rounded-full bg-amber-50 px-3 py-1 text-xs font-medium text-amber-800">Abono solicitado</span>
            @else
                <form method="POST" action="{{ route('asistente.abono') }}">
                    @csrf
                    <button class="rounded-lg border border-indigo-200 bg-indigo-50 px-3 py-1.5 text-sm text-indigo-700">
                        Quiero más preguntas (abono)
                    </button>
                </form>
            @endif
        @endif
    </div>

    <div class="flex h-[28rem] flex-col rounded-xl border border-slate-200 bg-white shadow-sm">
        <div class="flex-1 space-y-3 overflow-y-auto p-4" x-ref="scroll">
            @foreach ($mensajes as $m)
                <div class="{{ $m->rol === 'user' ? 'ml-8 text-right' : 'mr-8' }}">
                    <div class="inline-block rounded-2xl px-3 py-2 text-sm {{ $m->rol === 'user' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800' }}">
                        {!! nl2br(e($m->body)) !!}
                    </div>
                </div>
            @endforeach
            <template x-for="(m, i) in extras" :key="i">
                <div :class="m.rol === 'user' ? 'ml-8 text-right' : 'mr-8'">
                    <div class="inline-block rounded-2xl px-3 py-2 text-sm" :class="m.rol === 'user' ? 'bg-indigo-600 text-white' : 'bg-slate-100 text-slate-800'" x-text="m.body"></div>
                </div>
            </template>
        </div>
        <form class="border-t border-slate-100 p-3" @submit.prevent="enviar">
            <div class="flex gap-2">
                <input x-model="texto" :disabled="enviando || restantes <= 0" maxlength="2000"
                       placeholder="{{ $cupo['restantes'] > 0 ? 'Preguntá por un producto, precio, stock…' : 'Sin cupo este mes' }}"
                       class="flex-1 rounded-lg border border-slate-300 px-3 py-2 text-sm">
                <button :disabled="enviando || restantes <= 0"
                        class="rounded-lg bg-indigo-600 px-4 py-2 text-sm font-semibold text-white disabled:opacity-50">
                    Enviar
                </button>
            </div>
            <p class="mt-1 text-xs text-red-600" x-show="error" x-text="error"></p>
        </form>
    </div>
</div>

<script>
function asistenteChat() {
    return {
        texto: '',
        enviando: false,
        error: '',
        restantes: {{ (int) $cupo['restantes'] }},
        extras: [],
        async enviar() {
            const msg = this.texto.trim();
            if (!msg) return;
            this.enviando = true;
            this.error = '';
            this.extras.push({ rol: 'user', body: msg });
            this.texto = '';
            this.$nextTick(() => this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight);
            try {
                const res = await fetch(@json(route('asistente.preguntar')), {
                    method: 'POST',
                    headers: {
                        'Content-Type': 'application/json',
                        'Accept': 'application/json',
                        'X-CSRF-TOKEN': document.querySelector('meta[name=csrf-token]')?.content,
                    },
                    body: JSON.stringify({ mensaje: msg }),
                });
                const data = await res.json();
                this.extras.push({ rol: 'assistant', body: data.texto || 'Sin respuesta' });
                if (data.cupo) this.restantes = data.cupo.restantes;
                if (!data.ok) this.error = data.texto;
            } catch (e) {
                this.error = 'No se pudo enviar.';
            } finally {
                this.enviando = false;
                this.$nextTick(() => this.$refs.scroll.scrollTop = this.$refs.scroll.scrollHeight);
            }
        }
    }
}
</script>
@endsection
