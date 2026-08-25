@props([
    'url',
    'name',
    'label' => null,
    'placeholder' => 'Escribí para buscar…',
    'value' => null,
    'valueLabel' => null,
    'required' => false,
    'minLength' => 2,
    'params' => [],
    'hint' => 'Mín. 2 caracteres · Enter para elegir',
    'inputClass' => 'w-full rounded-lg border border-slate-300 px-3 py-2 text-sm focus:border-indigo-500 focus:outline-none focus:ring-2 focus:ring-indigo-200',
])

@php
    $paramsJson = json_encode($params, JSON_UNESCAPED_UNICODE);
@endphp

<div
    {{ $attributes->class(['relative']) }}
    x-data="buscadorPredictivo({
        url: @js($url),
        name: @js($name),
        placeholder: @js($placeholder),
        minLength: {{ (int) $minLength }},
        params: {{ $paramsJson }},
        initialId: @js($value),
        initialLabel: @js($valueLabel),
        required: {{ $required ? 'true' : 'false' }},
    })"
    @click.outside="abierto = false"
>
    @if ($label)
        <label class="mb-1 block text-sm font-medium text-slate-700">{{ $label }}@if($required) * @endif</label>
    @endif

    <div class="relative">
        <input type="text" x-ref="input" x-model="q"
               @input.debounce.50ms="onInput()"
               @keydown="onKeydown($event)"
               @focus="if (items.length) abierto = true"
               @blur="onBlur()"
               :placeholder="placeholder"
               autocomplete="off"
               class="{{ $inputClass }} pr-16"
               @if ($required) :required="! selectedId" @endif>

        <input type="hidden" :name="name" :value="selectedId" @if ($required) required @endif>

        <div class="absolute inset-y-0 right-0 flex items-center gap-1 pr-2">
            <span x-show="cargando" class="text-xs text-slate-400">…</span>
            <button type="button" x-show="allowClear && (q || selectedId)" x-cloak @mousedown.prevent="limpiar()"
                    class="rounded p-1 text-slate-400 hover:bg-slate-100 hover:text-slate-600" title="Limpiar">✕</button>
        </div>
    </div>

    <ul x-show="abierto && items.length" x-cloak
        class="absolute z-30 mt-1 max-h-60 w-full overflow-auto rounded-lg border border-slate-200 bg-white py-1 text-sm shadow-lg">
        <template x-for="(item, i) in items" :key="item.id">
            <li>
                <button type="button"
                        class="flex w-full flex-col px-3 py-2 text-left hover:bg-indigo-50"
                        :class="i === indice ? 'bg-indigo-50' : ''"
                        @mousedown.prevent="elegir(item)">
                    <span class="font-medium text-slate-800" x-text="item.label"></span>
                </button>
            </li>
        </template>
    </ul>

    @if ($hint)
        <p class="mt-1 text-xs text-slate-400">{{ $hint }}</p>
    @endif
</div>
