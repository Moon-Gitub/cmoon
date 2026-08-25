@props([
    'url',
    'name' => 'buscar',
    'placeholder' => 'Buscar…',
    'value' => '',
    'gotoKey' => 'url',
    'navigate' => false,
    'hint' => 'Escribí 2+ letras · Enter filtra · ↑↓ Enter elige sugerencia',
])

<div class="relative min-w-[16rem] flex-1 sm:max-w-md"
     x-data="buscadorPredictivo({
        url: @js($url),
        name: @js($name),
        placeholder: @js($placeholder),
        minLength: 2,
        initialLabel: @js($value),
        allowClear: true,
        filterMode: true,
        onSelect(item) {
            @if ($navigate)
            if (item.{{ $gotoKey }}) {
                window.location = item.{{ $gotoKey }};
                return;
            }
            @endif
            const form = this.$el.closest('form');
            const input = form?.querySelector('[name={{ $name }}]');
            const valor = this.valorFiltro(item);
            if (input) input.value = valor;
            this.q = String(valor);
            if (!form) return;
            if (typeof form.requestSubmit === 'function') form.requestSubmit();
            else form.submit();
        },
     })"
     @click.outside="abierto = false">
    <div class="relative">
        <input type="text" name="{{ $name }}" x-ref="input" x-model="q"
               @input.debounce.50ms="onInput()"
               @keydown="onKeydown($event)"
               @focus="if (items.length) abierto = true"
               @blur="onBlur()"
               placeholder="{{ $placeholder }}"
               title="{{ $hint }}"
               autocomplete="off"
               class="h-[38px] w-full rounded-lg border border-slate-300 py-2 pl-3 pr-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
        <button type="button" x-show="q" x-cloak @mousedown.prevent="limpiar()"
                class="absolute inset-y-0 right-0 px-2 text-slate-400 hover:text-slate-600">✕</button>
    </div>
    <ul x-show="abierto && items.length" x-cloak
        class="absolute z-30 mt-1 max-h-64 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
        <template x-for="(item, i) in items" :key="item.id">
            <li>
                <button type="button" class="block w-full px-3 py-2 text-left hover:bg-indigo-50"
                        :class="i === indice ? 'bg-indigo-50' : ''"
                        @mousedown.prevent="elegir(item)">
                    <span class="font-medium text-slate-800" x-text="item.label"></span>
                </button>
            </li>
        </template>
    </ul>
    {{-- Hint absoluto: no estira la fila ni desalinea selects/botones (items-center). --}}
    @if ($hint)
        <p class="pointer-events-none absolute left-0 top-full z-10 mt-0.5 text-[11px] leading-tight text-slate-400">{{ $hint }}</p>
    @endif
</div>
