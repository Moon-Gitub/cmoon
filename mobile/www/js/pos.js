(() => {
    const state = {
        config: null,
        catalog: null,
        carrito: [],
        seleccion: 0,
        sugerencias: [],
        canSell: true,
        scanMode: false,
    };

    const $ = (id) => document.getElementById(id);
    const fmt = (n) => '$ ' + n.toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    async function init() {
        if (! await window.cmoon.isActivated()) {
            window.location.href = 'setup.html';
            return;
        }

        state.config = await window.cmoon.getConfig();
        $('empresa').textContent = state.config?.empresa_nombre || 'CMoon POS';
        $('sucursal').textContent = state.config?.sucursal_id ? `#${state.config.sucursal_id}` : '';

        await refreshLicenseUi();
        state.catalog = await window.cmoon.getCatalog();
        bindEvents();
        setInterval(tick, 30000);
        window.addEventListener('online', tick);
        window.addEventListener('offline', () => updateOnlinePill(false));
        tick();
        renderCarrito();
    }

    async function refreshLicenseUi() {
        const st = await window.cmoon.licenseStatus();
        state.canSell = st.can_sell;

        if (! st.can_sell) {
            $('blocked').hidden = false;
            $('blocked-msg').textContent = st.message || 'Licencia vencida o suspendida.';
            $('pos').style.opacity = '0.3';
            $('pos').style.pointerEvents = 'none';
        } else {
            $('blocked').hidden = true;
            $('pos').style.opacity = '1';
            $('pos').style.pointerEvents = 'auto';
        }

        $('license-msg').hidden = ! (st.message && st.can_sell);
        if (! $('license-msg').hidden) $('license-msg').textContent = st.message;
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
                await window.cmoon.syncAll().catch(() => {});
                await window.cmoon.syncCatalog().catch(() => {});
                state.catalog = await window.cmoon.getCatalog();
                serverOk = true;
            }
        } catch { /* sin servidor */ }
        updateOnlinePill(serverOk);
        await refreshLicenseUi();
        const n = await window.cmoon.pendingCount();
        $('pending').hidden = n === 0;
        if (n) $('pending').textContent = `${n} pend.`;
    }

    function toast(msg) {
        const el = $('scan-toast');
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { el.hidden = true; }, 1800);
    }

    function productos() { return state.catalog?.productos || []; }
    function medios() { return state.catalog?.medios || []; }

    /** Igual que POS legacy: cantidad*codigo o balanza EAN-13 */
    function procesarCodigo(raw) {
        let q = String(raw || '').trim();
        if (! q) return { ok: false, error: 'Código vacío' };

        let cantidad = 1;

        if (q.includes('*')) {
            const parts = q.split('*');
            if (parts.length === 2 && parts[0] && parts[1]) {
                cantidad = parseFloat(parts[0].replace(',', '.'));
                q = parts[1].trim();
            }
        }

        if (/^2\d{12}$/.test(q)) {
            const plu = q.slice(1, 6);
            const gramos = parseInt(q.slice(6, 11), 10);
            const p = productos().find(x => x.pesable && (x.codigo === plu || x.codigo === String(parseInt(plu, 10))));
            if (p && gramos > 0) {
                return { ok: true, producto: p, cantidad: gramos / 1000 };
            }
            return { ok: false, error: 'Producto pesable no encontrado' };
        }

        const exacto = productos().find(p => p.codigo.toLowerCase() === q.toLowerCase());
        if (exacto) return { ok: true, producto: exacto, cantidad };

        return { ok: false, error: `No encontrado: ${q}` };
    }

    function agregarProducto(p, cantidad = 1) {
        if (! p) return false;
        const ex = state.carrito.find(i => i.producto_id === p.id && ! p.pesable);
        if (ex && ! p.pesable) ex.cantidad += cantidad;
        else state.carrito.push({
            producto_id: p.id,
            codigo: p.codigo,
            nombre: p.nombre,
            cantidad,
            precio: p.precio,
            iva: p.iva,
        });
        renderCarrito();
        return true;
    }

    function agregarPorCodigo(raw) {
        const r = procesarCodigo(raw);
        if (! r.ok) {
            toast(r.error);
            return false;
        }
        agregarProducto(r.producto, r.cantidad);
        toast(`+ ${r.producto.nombre}`);
        return true;
    }

    function agregarPorEnter() {
        const q = $('buscar').value.trim();
        if (! q) return;
        if (agregarPorCodigo(q)) {
            $('buscar').value = '';
            state.sugerencias = [];
            renderSug();
        }
    }

    function filtrar() {
        const q = $('buscar').value.trim().toLowerCase();
        state.seleccion = 0;
        if (q.length < 2 || q.includes('*') || /^\d+$/.test(q)) {
            state.sugerencias = [];
            renderSug();
            return;
        }
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
            el.addEventListener('click', () => {
                agregarProducto(state.sugerencias[+el.dataset.i]);
                $('buscar').value = '';
                state.sugerencias = [];
                renderSug();
            });
        });
    }

    function total() {
        return Math.round(state.carrito.reduce((s, i) => s + i.cantidad * i.precio, 0) * 100) / 100;
    }

    function renderCarrito() {
        $('carrito').innerHTML = state.carrito.map(i =>
            `<div class="cart-row"><span>${i.nombre} × ${i.cantidad}</span><span>${fmt(i.cantidad * i.precio)}</span></div>`
        ).join('') || '<p class="muted">Carrito vacío</p>';
        $('total').textContent = fmt(total());
        $('cart-count').textContent = state.carrito.length;
        $('btn-cobrar').disabled = ! state.carrito.length || ! state.canSell;
    }

    async function iniciarEscaneo() {
        try {
            state.scanMode = true;
            $('scan-overlay').hidden = false;
            $('btn-escanear').hidden = true;
            $('btn-escanear-uno').hidden = true;
            $('btn-teclado').hidden = false;
            await window.cmoonScanner.startScanMode(agregarPorCodigo);
        } catch (err) {
            state.scanMode = false;
            $('scan-overlay').hidden = true;
            alert(err.message);
        }
    }

    async function escanearUnCodigo() {
        try {
            const code = await window.cmoonScanner.scanOne();
            agregarPorCodigo(code);
        } catch (err) {
            if (String(err.message || '').toLowerCase().includes('cancel')) return;
            alert(err.message || 'No se pudo escanear.');
        }
    }

    async function terminarEscaneo() {
        state.scanMode = false;
        $('scan-overlay').hidden = true;
        $('btn-escanear').hidden = false;
        $('btn-escanear-uno').hidden = false;
        $('btn-teclado').hidden = true;
        await window.cmoonScanner.stopScanMode();
        $('buscar').focus();
    }

    function abrirPago() {
        if (state.scanMode) terminarEscaneo();
        $('medio').innerHTML = medios().map(x => `<option value="${x.id}">${x.nombre}</option>`).join('');
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
            origen: 'mobile',
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
            $('ok-title').textContent = result.online ? 'Venta registrada' : 'Venta guardada sin conexión';
            $('ok-msg').textContent = result.online
                ? `Comprobante #${result.numero ?? '—'} · ${fmt(importe)}`
                : `Total ${fmt(importe)}. Se sincronizará al volver internet.`;
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

    function bindEvents() {
        $('buscar').addEventListener('input', filtrar);
        $('buscar').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') { e.preventDefault(); agregarPorEnter(); }
        });
        $('btn-vaciar').addEventListener('click', () => { state.carrito = []; renderCarrito(); });
        $('btn-cobrar').addEventListener('click', abrirPago);
        $('btn-sync').addEventListener('click', tick);
        $('btn-home').addEventListener('click', () => window.cmoon.openHome());
        $('btn-retry-license').addEventListener('click', tick);
        $('btn-escanear').addEventListener('click', iniciarEscaneo);
        $('btn-escanear-uno').addEventListener('click', escanearUnCodigo);
        $('btn-overlay-stop').addEventListener('click', terminarEscaneo);
        $('btn-teclado').addEventListener('click', () => { $('buscar').focus(); toast('Listo para lector USB/BT'); });
        $('cancel-pago').addEventListener('click', () => $('modal-pago').close());
        $('form-pago').addEventListener('submit', confirmarVenta);
        $('ok-close').addEventListener('click', () => { $('modal-ok').close(); $('buscar').focus(); });
    }

    init().catch(err => alert(err.message));
})();
