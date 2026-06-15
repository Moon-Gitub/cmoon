(() => {
    const state = {
        config: null,
        catalog: null,
        carrito: [],
        seleccion: 0,
        sugerencias: [],
        canSell: true,
    };

    const $ = (id) => document.getElementById(id);
    const fmt = (n) => '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function init() {
        state.config = await window.cmoon.getConfig();
        $('empresa').textContent = state.config?.empresa_nombre || 'POSMoon';
        $('sucursal').textContent = state.config?.sucursal_id ? `Sucursal #${state.config.sucursal_id}` : '';

        await refreshLicenseUi();
        state.catalog = await window.cmoon.getCatalog();
        bindEvents();
        setInterval(tick, 30000);
        window.addEventListener('online', tick);
        window.addEventListener('offline', () => updateOnlinePill(false));
        tick();
    }

    async function refreshLicenseUi() {
        const st = await window.cmoon.licenseStatus();
        state.canSell = st.can_sell;

        if (! st.can_sell) {
            $('blocked').hidden = false;
            $('blocked-msg').textContent = st.message || 'Licencia vencida o suspendida. Conecte a internet y regularice su abono Moon.';
            $('pos').style.opacity = '0.3';
            $('pos').style.pointerEvents = 'none';
        } else {
            $('blocked').hidden = true;
            $('pos').style.opacity = '1';
            $('pos').style.pointerEvents = 'auto';
        }

        if (st.message && st.can_sell) {
            $('license-msg').hidden = false;
            $('license-msg').textContent = st.message;
        } else {
            $('license-msg').hidden = true;
        }
    }

    function updateOnlinePill(serverOk) {
        $('online').textContent = serverOk ? 'En línea' : 'Sin conexión';
        $('online').className = 'pill ' + (serverOk ? 'ok' : 'off');
    }

    async function tick() {
        let serverOk = false;
        try {
            if (navigator.onLine) {
                await window.cmoon.refreshLicense();
                await window.cmoon.syncSales().catch(() => {});
                await window.cmoon.syncCatalog().catch(() => {});
                state.catalog = await window.cmoon.getCatalog();
                serverOk = true;
            }
        } catch { /* sin servidor */ }
        updateOnlinePill(serverOk);
        await refreshLicenseUi();
        const n = await window.cmoon.pendingCount();
        $('pending').hidden = n === 0;
        if (n) $('pending').textContent = `${n} venta(s) por sincronizar`;
    }

    function bindEvents() {
        $('buscar').addEventListener('input', filtrar);
        $('buscar').addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { state.seleccion = Math.min(state.seleccion + 1, state.sugerencias.length - 1); renderSug(); }
            if (e.key === 'ArrowUp') { state.seleccion = Math.max(state.seleccion - 1, 0); renderSug(); }
            if (e.key === 'Enter') { e.preventDefault(); agregarPorEnter(); }
        });
        $('btn-vaciar').addEventListener('click', () => { state.carrito = []; renderCarrito(); });
        $('btn-cobrar').addEventListener('click', abrirPago);
        $('btn-sync').addEventListener('click', tick);
        $('btn-retry-license').addEventListener('click', tick);
        $('cancel-pago').addEventListener('click', () => $('modal-pago').close());
        $('form-pago').addEventListener('submit', confirmarVenta);
        $('ok-close').addEventListener('click', () => $('modal-ok').close());
        window.addEventListener('keydown', (e) => {
            if (e.key === 'F12' && state.carrito.length && state.canSell) abrirPago();
        });
    }

    function productos() { return state.catalog?.productos || []; }
    function medios() { return state.catalog?.medios || []; }

    function filtrar() {
        const q = $('buscar').value.trim().toLowerCase();
        state.seleccion = 0;
        if (q.length < 2) { state.sugerencias = []; renderSug(); return; }
        state.sugerencias = productos().filter(p =>
            p.nombre.toLowerCase().includes(q) || p.codigo.toLowerCase().includes(q)
        ).slice(0, 8);
        renderSug();
    }

    function renderSug() {
        $('sugerencias').innerHTML = state.sugerencias.map((p, i) =>
            `<div class="sug-item ${i === state.seleccion ? 'active' : ''}" data-i="${i}">${p.nombre} <small>${p.codigo}</small> — ${fmt(p.precio)}</div>`
        ).join('');
        $('sugerencias').querySelectorAll('.sug-item').forEach(el => {
            el.addEventListener('click', () => agregar(state.sugerencias[+el.dataset.i]));
        });
    }

    function agregarPorEnter() {
        const q = $('buscar').value.trim();
        if (! q) return;
        if (/^2\d{12}$/.test(q)) {
            const plu = q.slice(1, 6);
            const gramos = parseInt(q.slice(6, 11), 10);
            const p = productos().find(x => x.pesable && (x.codigo === plu || x.codigo === String(parseInt(plu, 10))));
            if (p && gramos > 0) {
                state.carrito.push({ producto_id: p.id, codigo: q, nombre: p.nombre, cantidad: gramos / 1000, precio: p.precio, iva: p.iva });
                $('buscar').value = '';
                state.sugerencias = [];
                renderSug(); renderCarrito();
                return;
            }
        }
        const exacto = productos().find(p => p.codigo.toLowerCase() === q.toLowerCase());
        if (exacto) { agregar(exacto); return; }
        if (state.sugerencias.length) agregar(state.sugerencias[state.seleccion]);
    }

    function agregar(p) {
        const ex = state.carrito.find(i => i.producto_id === p.id);
        if (ex && ! p.pesable) ex.cantidad += 1;
        else state.carrito.push({ producto_id: p.id, codigo: p.codigo, nombre: p.nombre, cantidad: 1, precio: p.precio, iva: p.iva });
        $('buscar').value = '';
        state.sugerencias = [];
        renderSug();
        renderCarrito();
        $('buscar').focus();
    }

    function total() {
        return Math.round(state.carrito.reduce((s, i) => s + i.cantidad * i.precio, 0) * 100) / 100;
    }

    function renderCarrito() {
        $('carrito').innerHTML = state.carrito.map(i =>
            `<div class="cart-row"><span>${i.nombre} × ${i.cantidad}</span><span>${fmt(i.cantidad * i.precio)}</span></div>`
        ).join('') || '<p class="muted">Carrito vacío</p>';
        $('total').textContent = fmt(total());
        $('btn-cobrar').disabled = ! state.carrito.length || ! state.canSell;
    }

    function abrirPago() {
        const m = medios();
        $('medio').innerHTML = m.map(x => `<option value="${x.id}">${x.nombre}</option>`).join('');
        $('importe').value = total();
        $('pago-total').textContent = fmt(total());
        $('pago-error').hidden = true;
        $('modal-pago').showModal();
    }

    async function confirmarVenta(e) {
        e.preventDefault();
        const importe = parseFloat($('importe').value);
        if (Math.abs(importe - total()) > 0.01) {
            $('pago-error').textContent = 'El importe debe coincidir con el total.';
            $('pago-error').hidden = false;
            return;
        }

        const payload = {
            uuid: crypto.randomUUID(),
            sucursal_id: state.config.sucursal_id,
            origen: 'desktop',
            fecha: new Date().toISOString(),
            items: state.carrito.map(i => ({
                producto_id: i.producto_id,
                descripcion: i.nombre,
                cantidad: i.cantidad,
                precio_unitario: i.precio,
                alicuota_iva: i.iva,
            })),
            pagos: [{ medio_pago_id: parseInt($('medio').value, 10), importe }],
        };

        try {
            const result = await window.cmoon.submitSale(payload);
            if (result.online) {
                $('ok-title').textContent = 'Venta registrada';
                $('ok-msg').textContent = `Comprobante #${result.numero ?? '—'} · ${fmt(total())}`;
            } else {
                $('ok-title').textContent = 'Venta guardada sin conexión';
                $('ok-msg').textContent = `Total ${fmt(total())}. Se sincronizará cuando haya internet.`;
            }
            $('modal-pago').close();
            $('modal-ok').showModal();
            state.carrito = [];
            renderCarrito();
            tick();
        } catch (err) {
            $('pago-error').textContent = err.message;
            $('pago-error').hidden = false;
        }
    }

    init().catch(err => alert(err.message));
})();
