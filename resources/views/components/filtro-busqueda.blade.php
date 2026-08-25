@props([
    'url',
    'name' => 'buscar',
    'placeholder' => 'Buscar…',
    'value' => '',
    'gotoKey' => 'url',
    'navigate' => true,
    'hint' => 'Escribí 2+ letras · ↑↓ Enter · o Enter para filtrar la tabla',
])

<div class="relative min-w-[16rem] flex-1 sm:max-w-md"
     x-data="buscadorPredictivo({
        url: @js($url),
        name: '',
        placeholder: @js($placeholder),
        minLength: 2,
        initialLabel: @js($value),
        allowClear: true,
        onSelect(item) {
            @if ($navigate)
            if (item.{{ $gotoKey }}) {
                window.location = item.{{ $gotoKey }};
                return;
            }
            @endif
            const form = this.$el.closest('form');
            const input = form?.querySelector('[name={{ $name }}]');
            if (input) {
                input.value = item.nombre || item.razon_social || item.label || this.q;
                form.submit();
            }
        },
     })"
     @click.outside="abierto = false">
    <div class="relative">
        <input type="text" name="{{ $name }}" x-ref="input" x-model="q"
               @input.debounce.50ms="onInput()"
               @keydown="onKeydown($event)"
               @keydown.enter.prevent="
                    if (abierto && indice >= 0 && items[indice]) { elegir(items[indice]); }
                    else { $el.form?.submit(); }
               "
               @focus="if (items.length) abierto = true"
               @blur="onBlur()"
               placeholder="{{ $placeholder }}"
               autocomplete="off"
               class="w-full rounded-lg border border-slate-300 py-2 pl-3 pr-9 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200">
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
    <p class="mt-1 text-[11px] text-slate-400">{{ $hint }}</p>
</div>
