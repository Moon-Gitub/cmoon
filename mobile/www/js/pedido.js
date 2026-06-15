(() => {
    const state = {
        config: null,
        catalog: null,
        carrito: [],
        seleccion: 0,
        sugerencias: [],
        clientesFiltrados: [],
        clienteId: null,
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
        if (! state.config?.can_pedidos) {
            alert('Este usuario no puede tomar pedidos.');
            window.location.href = 'home.html';
            return;
        }

        $('empresa').textContent = state.config?.empresa_nombre || 'POSMoon';
        state.catalog = await window.cmoon.getCatalog();
        renderClientes(clientes());
        bindEvents();
        const pre = new URLSearchParams(location.search).get('cliente');
        if (pre) {
            state.clienteId = pre;
            $('cliente').value = pre;
        }
        setInterval(tick, 30000);
        window.addEventListener('online', tick);
        window.addEventListener('offline', () => updateOnlinePill(false));
        tick();
        renderCarrito();
    }

    function clientes() { return state.catalog?.clientes || []; }
    function productos() { return state.catalog?.productos || []; }
    function listas() { return state.catalog?.listas || []; }

    function precioDe(prod) {
        const c = clientes().find(x => Number(x.id) === Number(state.clienteId));
        if (c?.lista_precio_id) {
            const lista = listas().find(l => Number(l.id) === Number(c.lista_precio_id));
            if (lista) {
                return Math.round(prod.precio * (1 + lista.porcentaje / 100) * 100) / 100;
            }
        }
        return prod.precio;
    }

    function renderClientes(lista) {
        state.clientesFiltrados = lista.slice(0, 30);
        $('cliente').innerHTML = state.clientesFiltrados.map(c =>
            `<option value="${c.id}">${c.nombre}${c.documento ? ' · ' + c.documento : ''}</option>`
        ).join('');
        if (state.clientesFiltrados.length && ! state.clienteId) {
            state.clienteId = state.clientesFiltrados[0].id;
            $('cliente').value = state.clienteId;
        }
    }

    function filtrarClientes() {
        const q = $('buscar-cliente').value.trim().toLowerCase();
        if (q.length < 2) {
            renderClientes(clientes());
            return;
        }
        renderClientes(clientes().filter(c =>
            c.nombre.toLowerCase().includes(q) || String(c.documento || '').includes(q)
        ));
    }

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
        const precio = precioDe(p);
        const ex = state.carrito.find(i => i.producto_id === p.id && ! p.pesable);
        if (ex && ! p.pesable) ex.cantidad += cantidad;
        else state.carrito.push({
            producto_id: p.id,
            codigo: p.codigo,
            nombre: p.nombre,
            cantidad,
            precio,
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

    function filtrarProductos() {
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
            `<div class="sug-item ${i === state.seleccion ? 'active' : ''}" data-i="${i}">${p.nombre} <small>${p.codigo}</small> — ${fmt(precioDe(p))}</div>`
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
        ).join('') || '<p class="muted">Sin ítems</p>';
        $('total').textContent = fmt(total());
        $('cart-count').textContent = state.carrito.length;
        $('btn-enviar').disabled = ! state.carrito.length || ! state.clienteId;
    }

    async function enviarPedido() {
        if (! state.clienteId) {
            alert('Seleccioná un cliente.');
            return;
        }

        const payload = {
            uuid: crypto.randomUUID(),
            cliente_id: parseInt(state.clienteId, 10),
            observaciones: $('observaciones').value.trim() || null,
            items: state.carrito.map(i => ({
                producto_id: i.producto_id,
                descripcion: i.nombre,
                cantidad: i.cantidad,
                precio_unitario: i.precio,
            })),
        };

        try {
            const result = await window.cmoon.submitPedido(payload);
            $('ok-title').textContent = result.online ? 'Pedido enviado' : 'Pedido guardado sin conexión';
            $('ok-msg').textContent = result.online
                ? `Presupuesto #${String(result.numero ?? '').padStart(6, '0')} · ${fmt(total())}\nQueda pendiente de aprobación.`
                : `Total ${fmt(total())}. Se sincronizará al volver internet.`;
            $('modal-ok').showModal();
            state.carrito = [];
            $('observaciones').value = '';
            renderCarrito();
            tick();
        } catch (err) {
            alert(err.message);
        }
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
        const n = await window.cmoon.pendingCount();
        $('pending').hidden = n === 0;
        if (n) $('pending').textContent = `${n} pend.`;
    }

    function updateOnlinePill(serverOk) {
        $('online').textContent = serverOk ? 'En línea' : 'Sin conexión';
        $('online').className = 'pill ' + (serverOk ? 'ok' : 'off');
    }

    function toast(msg) {
        const el = $('scan-toast');
        el.textContent = msg;
        el.hidden = false;
        clearTimeout(toast._t);
        toast._t = setTimeout(() => { el.hidden = true; }, 1800);
    }

    async function iniciarEscaneo() {
        try {
            state.scanMode = true;
            $('scan-overlay').hidden = false;
            await window.cmoonScanner.startScanMode(agregarPorCodigo);
        } catch (err) {
            state.scanMode = false;
            $('scan-overlay').hidden = true;
            alert(err.message);
        }
    }

    async function escanearUnCodigo() {
        try {
            agregarPorCodigo(await window.cmoonScanner.scanOne());
        } catch (err) {
            if (! String(err.message || '').toLowerCase().includes('cancel')) alert(err.message);
        }
    }

    async function terminarEscaneo() {
        state.scanMode = false;
        $('scan-overlay').hidden = true;
        await window.cmoonScanner.stopScanMode();
    }

    function bindEvents() {
        $('btn-home').addEventListener('click', () => window.cmoon.openHome());
        $('buscar-cliente').addEventListener('input', filtrarClientes);
        $('cliente').addEventListener('change', (e) => { state.clienteId = e.target.value; renderCarrito(); });
        $('buscar').addEventListener('input', filtrarProductos);
        $('buscar').addEventListener('keydown', (e) => {
            if (e.key === 'Enter') {
                e.preventDefault();
                const q = $('buscar').value.trim();
                if (q && agregarPorCodigo(q)) $('buscar').value = '';
            }
        });
        $('btn-vaciar').addEventListener('click', () => { state.carrito = []; renderCarrito(); });
        $('btn-enviar').addEventListener('click', enviarPedido);
        $('btn-sync').addEventListener('click', tick);
        $('btn-escanear').addEventListener('click', iniciarEscaneo);
        $('btn-escanear-uno').addEventListener('click', escanearUnCodigo);
        $('btn-overlay-stop').addEventListener('click', terminarEscaneo);
        $('ok-close').addEventListener('click', () => { $('modal-ok').close(); $('buscar').focus(); });
    }

    init().catch(err => alert(err.message));
})();
