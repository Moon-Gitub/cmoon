/**
 * Buscador predictivo reutilizable (estilo demonew / jQuery UI Autocomplete).
 *
 * Modos:
 * - Formulario (default): elige un ítem → selectedId + evento.
 * - Filtro de listado (filterMode): Enter sin flechas filtra la tabla;
 *   ↑↓ + Enter o click elige sugerencia.
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
        filterMode: !!config.filterMode,
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
                // Sin preselección: Enter filtra; ↑↓ elige ítem.
                this.indice = -1;
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
            this.q = this.filterMode
                ? this.valorFiltro(item)
                : (this.selectedLabel);
            this.abierto = false;
            this.items = [];
            this.indice = -1;
            if (this.onSelect) this.onSelect(item);
            this.$dispatch('buscador-seleccionado', { name: this.name, item });
        },

        /** Valor seguro para GET ?buscar= (nunca el label "código — nombre"). */
        valorFiltro(item) {
            if (item.codigo) return String(item.codigo);
            if (item.documento) return String(item.documento);
            if (item.cuit) return String(item.cuit);
            if (item.nombre) return String(item.nombre);
            if (item.razon_social) return String(item.razon_social);
            if (item.numero != null && item.numero !== '') return String(item.numero);
            return this.limpiarTermino(this.q);
        },

        limpiarTermino(texto) {
            return String(texto || '')
                .replace(/\s+[—\-–]\s+.*/u, '')
                .trim();
        },

        submitFiltro() {
            this.q = this.limpiarTermino(this.q);
            this.abierto = false;
            this.items = [];
            this.indice = -1;
            const form = this.$el?.closest?.('form');
            if (!form) return;
            const input = this.$refs.input;
            if (input) input.value = this.q;
            if (typeof form.requestSubmit === 'function') {
                form.requestSubmit();
            } else {
                form.submit();
            }
        },

        limpiar() {
            this.q = '';
            this.selectedId = '';
            this.selectedLabel = '';
            this.items = [];
            this.abierto = false;
            this.indice = -1;
            this.$refs.input?.focus();
            if (this.filterMode) {
                this.$nextTick(() => this.submitFiltro());
            }
        },

        onKeydown(e) {
            if (!this.abierto && ['ArrowDown', 'ArrowUp'].includes(e.key) && this.items.length) {
                this.abierto = true;
            }
            if (e.key === 'ArrowDown' && this.items.length) {
                e.preventDefault();
                this.abierto = true;
                this.indice = this.indice < 0 ? 0 : (this.indice + 1) % this.items.length;
            } else if (e.key === 'ArrowUp' && this.items.length) {
                e.preventDefault();
                this.abierto = true;
                this.indice = this.indice < 0
                    ? this.items.length - 1
                    : (this.indice - 1 + this.items.length) % this.items.length;
            } else if (e.key === 'Enter') {
                if (this.abierto && this.indice >= 0 && this.items[this.indice]) {
                    e.preventDefault();
                    this.elegir(this.items[this.indice]);
                } else if (this.filterMode) {
                    e.preventDefault();
                    this.submitFiltro();
                }
            } else if (e.key === 'Escape') {
                this.abierto = false;
                this.indice = -1;
            }
        },

        onBlur() {
            setTimeout(() => {
                this.abierto = false;
                if (this.selectedId && this.selectedLabel && !this.filterMode) {
                    this.q = this.selectedLabel;
                }
            }, 150);
        },
    };
}
