/**
 * Buscador predictivo reutilizable (estilo demonew / jQuery UI Autocomplete).
 *
 * Uso:
 *   <div x-data="buscadorPredictivo({ url: '...', name: 'cliente_id', minLength: 2 })" ...>
 */
export function buscadorPredictivo(config = {}) {
    return {
        url: config.url || '',
        name: config.name || '',
        placeholder: config.placeholder || 'Escribí para buscar…',
        minLength: config.minLength ?? 2,
        debounceMs: config.debounceMs ?? 280,
        limit: config.limit ?? 20,
        params: config.params || {},
        initialId: config.initialId ?? '',
        initialLabel: config.initialLabel ?? '',
        required: !!config.required,
        allowClear: config.allowClear !== false,
        onSelect: typeof config.onSelect === 'function' ? config.onSelect : null,

        q: config.initialLabel || '',
        selectedId: config.initialId ? String(config.initialId) : '',
        selectedLabel: config.initialLabel || '',
        items: [],
        abierto: false,
        cargando: false,
        indice: -1,
        _timer: null,
        _req: 0,

        init() {
            if (this.selectedLabel) {
                this.q = this.selectedLabel;
            }
        },

        onInput() {
            this.selectedId = '';
            this.selectedLabel = '';
            this.indice = -1;
            clearTimeout(this._timer);
            const term = this.q.trim();
            if (term.length < this.minLength) {
                this.items = [];
                this.abierto = false;
                this.cargando = false;
                return;
            }
            this.cargando = true;
            this._timer = setTimeout(() => this.buscar(term), this.debounceMs);
        },

        async buscar(term) {
            const req = ++this._req;
            try {
                const qs = new URLSearchParams({ q: term, limit: String(this.limit), ...this.params });
                const res = await fetch(`${this.url}?${qs}`, {
                    headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
                    credentials: 'same-origin',
                });
                if (req !== this._req) return;
                this.items = res.ok ? await res.json() : [];
                this.abierto = this.items.length > 0;
                this.indice = this.items.length ? 0 : -1;
            } catch {
                if (req !== this._req) return;
                this.items = [];
                this.abierto = false;
            } finally {
                if (req === this._req) this.cargando = false;
            }
        },

        elegir(item) {
            if (!item) return;
            this.selectedId = String(item.id);
            this.selectedLabel = item.label || item.nombre || '';
            this.q = this.selectedLabel;
            this.abierto = false;
            this.items = [];
            this.indice = -1;
            if (this.onSelect) this.onSelect(item);
            this.$dispatch('buscador-seleccionado', { name: this.name, item });
        },

        limpiar() {
            this.q = '';
            this.selectedId = '';
            this.selectedLabel = '';
            this.items = [];
            this.abierto = false;
            this.indice = -1;
            this.$refs.input?.focus();
        },

        onKeydown(e) {
            if (!this.abierto && ['ArrowDown', 'ArrowUp'].includes(e.key) && this.items.length) {
                this.abierto = true;
            }
            if (e.key === 'ArrowDown' && this.items.length) {
                e.preventDefault();
                this.indice = (this.indice + 1) % this.items.length;
            } else if (e.key === 'ArrowUp' && this.items.length) {
                e.preventDefault();
                this.indice = (this.indice - 1 + this.items.length) % this.items.length;
            } else if (e.key === 'Enter') {
                if (this.abierto && this.indice >= 0 && this.items[this.indice]) {
                    e.preventDefault();
                    this.elegir(this.items[this.indice]);
                }
            } else if (e.key === 'Escape') {
                this.abierto = false;
            }
        },

        onBlur() {
            setTimeout(() => {
                this.abierto = false;
                if (this.selectedId && this.selectedLabel) {
                    this.q = this.selectedLabel;
                }
            }, 150);
        },
    };
}
