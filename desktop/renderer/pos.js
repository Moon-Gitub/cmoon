(() => {
    const state = {
        config: null,
        catalog: null,
        carrito: [],
        seleccion: 0,
        sugerencias: [],
        canSell: true,
        clienteId: '',
    };

    const $ = (id) => document.getElementById(id);
    const fmt = (n) => '$ ' + (Number(n) || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });

    function parseNum(raw) {
        if (raw === null || raw === undefined) return null;
        let s = String(raw).trim().replace(/\s/g, '');
        if (s === '' || s === '.' || s === ',' || s === '-') return null;
        if (s.includes(',') && s.includes('.')) s = s.replace(/\./g, '').replace(',', '.');
        else if (s.includes(',')) s = s.replace(',', '.');
        const n = Number(s);
        return Number.isFinite(n) ? n : null;
    }

    async function init() {
        state.config = await window.cmoon.getConfig();
        $('empresa').textContent = state.config?.empresa_nombre || 'POSMoon';
        $('sucursal').textContent = state.config?.sucursal_id ? `Sucursal #${state.config.sucursal_id}` : '';

        await refreshLicenseUi();
        state.catalog = await window.cmoon.getCatalog();
        fillClientes();
        fillListas();
        bindEvents();
        setInterval(tick, 30000);
        window.addEventListener('online', tick);
        window.addEventListener('offline', () => updateOnlinePill(false));
        tick();
        $('buscar').focus();
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
                fillClientes();
                fillListas();
                serverOk = true;
            }
        } catch { /* sin servidor */ }
        updateOnlinePill(serverOk);
        await refreshLicenseUi();
        const n = await window.cmoon.pendingCount();
        $('pending').hidden = n === 0;
        if (n) $('pending').textContent = `${n} venta(s) por sincronizar`;
        renderCarrito();
    }

    function productos() { return state.catalog?.productos || []; }
    function medios() { return state.catalog?.medios || []; }
    function clientes() { return state.catalog?.clientes || []; }
    function listas() { return state.catalog?.listas || []; }
    function balanzasFormatos() { return state.catalog?.balanzas_formatos || []; }

    function interpretarCodigoBalanza(codigo, cantidadManual) {
        const formatos = balanzasFormatos();
        if (! codigo || ! formatos.length) return null;
        const codigoStr = String(codigo);
        let mejor = null;
        for (const cfg of formatos) {
            if (! cfg?.prefijo) continue;
            const pref = String(cfg.prefijo);
            if (! codigoStr.startsWith(pref)) continue;
            if (cfg.longitud_min && codigoStr.length < cfg.longitud_min) continue;
            if (cfg.longitud_max && codigoStr.length > cfg.longitud_max) continue;
            if (! mejor || String(mejor.prefijo).length < pref.length) mejor = cfg;
        }
        if (! mejor) return null;

        const posProd = parseInt(mejor.pos_producto, 10) || 0;
        const lenProd = parseInt(mejor.longitud_producto, 10) || 0;
        if (lenProd <= 0) return null;
        const idProducto = codigoStr.substr(posProd, lenProd);

        const modo = mejor.modo_cantidad || 'ninguno';
        let cantidad = 0;
        if (modo === 'peso') {
            if (mejor.pos_cantidad === null || mejor.pos_cantidad === undefined || ! mejor.longitud_cantidad) return null;
            const bruto = codigoStr.substr(parseInt(mejor.pos_cantidad, 10), parseInt(mejor.longitud_cantidad, 10));
            const num = parseFloat(bruto) || 0;
            let divisor = parseFloat(mejor.factor_divisor) || 1;
            if (! divisor) divisor = 1;
            cantidad = num / divisor;
        } else if (modo === 'unidad') {
            const fija = parseFloat(mejor.cantidad_fija);
            cantidad = (fija && fija > 0) ? fija : 1;
        } else {
            cantidad = parseFloat(cantidadManual) || 1;
        }
        if (! cantidad || cantidad <= 0) cantidad = 1;
        return { idProducto, cantidad };
    }

    function buscarPorCodigoProducto(codigoProd) {
        // Igual demonew: match exacto (32 ≠ 0032 ≠ 00032)
        const raw = String(codigoProd ?? '').trim();
        if (! raw) return null;

        let p = productos().find(x => String(x.codigo) === raw);
        if (p) return p;

        const stripped = raw.replace(/^0+/, '') || '0';
        if (stripped !== raw) {
            p = productos().find(x => String(x.codigo) === stripped);
            if (p) return p;
        }

        return productos().find(x => String(x.codigo).toLowerCase() === raw.toLowerCase()) || null;
    }

    function fillClientes() {
        const sel = $('cliente');
        const current = sel.value || state.clienteId;
        sel.innerHTML = '<option value="">Consumidor final</option>' +
            clientes().map(c => `<option value="${c.id}">${c.nombre}${c.documento ? ' · ' + c.documento : ''}</option>`).join('');
        sel.value = current || '';
        state.clienteId = sel.value;
        updateListaLabel();
    }

    function clienteActivo() {
        const id = Number($('cliente').value || 0);
        return clientes().find(c => Number(c.id) === id) || null;
    }

    function listaActiva() {
        const id = $('lista-precio')?.value;
        if (! id) return null;
        return listas().find(l => Number(l.id) === Number(id)) || null;
    }

    function fillListas() {
        const sel = $('lista-precio');
        if (! sel) return;
        const actual = sel.value;
        sel.innerHTML = '<option value="">General (precio de venta)</option>' +
            listas().map(l => {
                let extra = '';
                if (l.base === 'compra') {
                    extra = Number(l.porcentaje) === 0 ? ' — al costo' : ` — costo ${l.porcentaje > 0 ? '+' : ''}${l.porcentaje}%`;
                } else {
                    extra = ` — venta ${l.porcentaje > 0 ? '+' : ''}${l.porcentaje}%`;
                }
                return `<option value="${l.id}">${escapeHtml(l.nombre)}${extra}</option>`;
            }).join('');
        if (actual) sel.value = actual;
    }

    function updateListaLabel() {
        const lista = listaActiva();
        const el = $('lista-label');
        if (! el) return;
        if (! lista) { el.hidden = true; return; }
        el.hidden = false;
        if (lista.base === 'compra') {
            el.textContent = lista.porcentaje
                ? `Lista: ${lista.nombre} (costo ${lista.porcentaje > 0 ? '+' : ''}${lista.porcentaje}%)`
                : `Lista: ${lista.nombre} (al costo)`;
        } else {
            el.textContent = `Lista: ${lista.nombre} (${lista.porcentaje > 0 ? '+' : ''}${lista.porcentaje}%)`;
        }
    }

    function precioDe(prod) {
        const lista = listaActiva();
        const base = (lista && lista.base === 'compra')
            ? (Number(prod.precio_compra) || 0)
            : (Number(prod.precio) || 0);
        const precio = lista ? base * (1 + (Number(lista.porcentaje) || 0) / 100) : base;
        return Math.round(precio * 100) / 100;
    }

    function recalcularPreciosCarrito() {
        state.carrito.forEach(item => {
            const prod = productos().find(p => Number(p.id) === Number(item.producto_id));
            if (prod) {
                item.precio = precioDe(prod);
                item.pesable = !! prod.pesable;
            }
        });
        syncDescFromPctIfNeeded();
        renderCarrito();
    }

    function descuentoPct() {
        const n = parseNum($('descuento-pct')?.value);
        return n && n > 0 ? Math.min(100, n) : 0;
    }

    function descuento() {
        const n = parseNum($('descuento').value);
        return n && n > 0 ? n : 0;
    }

    function syncDescFromPct() {
        const pct = descuentoPct();
        const sub = subtotal();
        const monto = Math.round(sub * pct / 100 * 100) / 100;
        $('descuento').value = String(monto);
        if ($('descuento-pct')) $('descuento-pct').value = String(pct);
    }

    function syncDescFromMonto() {
        const sub = subtotal();
        let monto = descuento();
        if (sub > 0 && monto > sub) monto = sub;
        $('descuento').value = String(monto);
        const pct = sub > 0 ? Math.round(100 * monto / sub * 100) / 100 : 0;
        if ($('descuento-pct')) $('descuento-pct').value = String(pct);
    }

    function syncDescFromPctIfNeeded() {
        if (descuentoPct() > 0) syncDescFromPct();
    }

    function total() {
        return Math.max(0, Math.round((subtotal() - Math.min(subtotal(), descuento())) * 100) / 100);
    }

    function bindEvents() {
        $('buscar').addEventListener('input', filtrar);
        $('buscar').addEventListener('keydown', (e) => {
            if (e.key === 'ArrowDown') { state.seleccion = Math.min(state.seleccion + 1, state.sugerencias.length - 1); renderSug(); }
            if (e.key === 'ArrowUp') { state.seleccion = Math.max(state.seleccion - 1, 0); renderSug(); }
            if (e.key === 'Enter') { e.preventDefault(); agregarPorEnter(); }
        });
        $('cliente').addEventListener('change', () => {
            state.clienteId = $('cliente').value;
            const c = clienteActivo();
            if ($('lista-precio')) {
                $('lista-precio').value = c?.lista_precio_id ? String(c.lista_precio_id) : '';
            }
            updateListaLabel();
            recalcularPreciosCarrito();
        });
        $('lista-precio')?.addEventListener('change', () => {
            updateListaLabel();
            recalcularPreciosCarrito();
        });
        $('descuento-pct')?.addEventListener('input', () => { syncDescFromPct(); renderCarrito(); });
        $('descuento').addEventListener('input', () => { syncDescFromMonto(); renderCarrito(); });
        $('quick-disc')?.querySelectorAll('[data-pct]').forEach(btn => {
            btn.addEventListener('click', () => {
                if ($('descuento-pct')) $('descuento-pct').value = btn.dataset.pct;
                syncDescFromPct();
                renderCarrito();
            });
        });
        $('btn-vaciar').addEventListener('click', () => {
            state.carrito = [];
            if ($('descuento')) $('descuento').value = '0';
            if ($('descuento-pct')) $('descuento-pct').value = '0';
            renderCarrito();
        });
        $('btn-cobrar').addEventListener('click', abrirPago);
        $('btn-sync').addEventListener('click', tick);
        $('btn-retry-license').addEventListener('click', tick);
        $('cancel-pago').addEventListener('click', () => $('modal-pago').close());
        $('form-pago').addEventListener('submit', confirmarVenta);
        $('cancel-importe').addEventListener('click', () => {
            pendienteImporte = null;
            $('modal-importe').close();
            $('buscar').focus();
        });
        $('form-importe').addEventListener('submit', (e) => {
            e.preventDefault();
            if (! pendienteImporte) return;
            const precio = parseNum($('importe-precio').value);
            if (precio === null || precio <= 0) {
                $('importe-error').textContent = 'Ingresá un importe mayor a 0.';
                $('importe-error').hidden = false;
                return;
            }
            const { p, cantidad, codigo } = pendienteImporte;
            pushItem(p, cantidad, codigo, precio);
            if (p.pesable) state.carrito[state.carrito.length - 1].pesable = true;
            pendienteImporte = null;
            $('modal-importe').close();
            clearBuscar();
            renderCarrito();
        });
        $('medio').addEventListener('change', actualizarRecargoPago);
        $('importe').addEventListener('input', actualizarVuelto);
        $('ok-close').addEventListener('click', () => { $('modal-ok').close(); $('buscar').focus(); });
        window.addEventListener('keydown', (e) => {
            if (e.key === 'F12' && state.carrito.length && state.canSell) {
                e.preventDefault();
                abrirPago();
            }
        });
    }

    function filtrar() {
        const q = $('buscar').value.trim().toLowerCase();
        state.seleccion = 0;
        if (q.length < 2) { state.sugerencias = []; renderSug(); return; }
        state.sugerencias = productos().filter(p =>
            p.nombre.toLowerCase().includes(q) || String(p.codigo).toLowerCase().includes(q)
        ).slice(0, 8);
        renderSug();
    }

    function renderSug() {
        $('sugerencias').innerHTML = state.sugerencias.map((p, i) =>
            `<div class="sug-item ${i === state.seleccion ? 'active' : ''}" data-i="${i}">
                <span>${escapeHtml(p.nombre)} <small>${escapeHtml(p.codigo)}${p.pesable ? ' · KG' : ''}</small></span>
                <strong>${fmt(precioDe(p))}</strong>
            </div>`
        ).join('');
        $('sugerencias').querySelectorAll('.sug-item').forEach(el => {
            el.addEventListener('click', () => agregar(state.sugerencias[+el.dataset.i]));
        });
    }

    function escapeHtml(s) {
        return String(s ?? '').replace(/[&<>"']/g, c => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c]));
    }

    function agregarPorEnter() {
        const q = $('buscar').value.trim();
        if (! q) return;

        // cantidad*codigo
        const mult = q.match(/^(\d+(?:[.,]\d+)?)\s*[\*xX]\s*(.+)$/);
        if (mult) {
            const cant = parseNum(mult[1]);
            const p = buscarPorCodigoProducto(mult[2].trim());
            if (p && cant && cant > 0) {
                agregar(p, cant, p.codigo);
                return;
            }
        }

        // Formatos demonew / balanzas_formatos (Cabañas: 20000 + codigo 2 dígitos)
        const parsed = interpretarCodigoBalanza(q, 1);
        if (parsed?.idProducto) {
            const p = buscarPorCodigoProducto(parsed.idProducto);
            if (p) {
                agregar(p, parsed.cantidad, q);
                return;
            }
        }

        // Fallback EAN-13 genérico
        if (/^2\d{12}$/.test(q)) {
            const plu = q.slice(1, 6);
            const gramos = parseInt(q.slice(6, 11), 10);
            const p = buscarPorCodigoProducto(plu)
                || productos().find(x => x.pesable && (x.codigo === plu || x.codigo === String(parseInt(plu, 10))));
            if (p && gramos > 0) {
                agregar(p, gramos / 1000, q);
                return;
            }
        }

        const exacto = productos().find(p => String(p.codigo).toLowerCase() === q.toLowerCase());
        if (exacto) { agregar(exacto); return; }
        if (state.sugerencias.length) agregar(state.sugerencias[state.seleccion]);
    }

    function clearBuscar() {
        $('buscar').value = '';
        state.sugerencias = [];
        renderSug();
        renderCarrito();
        $('buscar').focus();
    }

    function pushItem(p, cantidad, codigo, precio) {
        state.carrito.push({
            producto_id: p.id,
            codigo: codigo || p.codigo,
            nombre: p.nombre,
            cantidad: Math.round(cantidad * 1000) / 1000,
            precio: Number(precio ?? precioDe(p)) || 0,
            iva: p.iva,
            pesable: !! p.pesable,
        });
    }

    let pendienteImporte = null;

    function pedirImporte(p, cantidad = 1, codigo) {
        pendienteImporte = { p, cantidad, codigo: codigo || p.codigo };
        $('importe-producto').textContent = `${p.codigo} — ${p.nombre}`;
        $('importe-precio').value = '';
        $('importe-error').hidden = true;
        $('buscar').value = '';
        state.sugerencias = [];
        renderSug();
        $('modal-importe').showModal();
        setTimeout(() => $('importe-precio').focus(), 50);
    }

    function agregar(p, cantidad = 1, codigo) {
        const cant = Math.max(0.001, Math.round((Number(cantidad) || 1) * 1000) / 1000);
        const precio = precioDe(p);
        if (! precio || precio <= 0) {
            pedirImporte(p, cant, codigo);
            return;
        }
        const ex = state.carrito.find(i => i.producto_id === p.id && ! p.pesable);
        if (ex) {
            ex.cantidad = Math.round((ex.cantidad + cant) * 1000) / 1000;
            ex.precio = precio;
        } else {
            pushItem(p, cant, codigo || p.codigo, precio);
        }
        clearBuscar();
    }

    function subtotal() {
        return Math.round(state.carrito.reduce((s, i) => s + i.cantidad * i.precio, 0) * 100) / 100;
    }

    function renderCarrito() {
        syncDescFromPctIfNeeded();
        $('carrito').innerHTML = state.carrito.map((i, idx) => `
            <div class="cart-row" data-idx="${idx}">
                <div class="cart-main">
                    <div class="cart-name">${escapeHtml(i.nombre)}</div>
                    <div class="cart-meta">${escapeHtml(i.codigo)}${i.pesable ? ' · KG' : ''}</div>
                </div>
                <div class="cart-qty">
                    <button type="button" class="qty-btn" data-act="-" data-idx="${idx}">−</button>
                    <input type="text" inputmode="decimal" class="qty-input" data-idx="${idx}" value="${i.cantidad}">
                    <button type="button" class="qty-btn" data-act="+" data-idx="${idx}">+</button>
                </div>
                <div class="cart-price">${fmt(i.cantidad * i.precio)}</div>
                <button type="button" class="cart-del" data-del="${idx}" title="Quitar">×</button>
            </div>
        `).join('') || '<p class="muted">Carrito vacío — buscá o escaneá un producto</p>';

        $('carrito').querySelectorAll('.qty-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const idx = +btn.dataset.idx;
                const item = state.carrito[idx];
                const paso = item.pesable ? 0.01 : 1;
                const dir = btn.dataset.act === '+' ? 1 : -1;
                item.cantidad = Math.max(0.001, Math.round((item.cantidad + dir * paso) * 1000) / 1000);
                renderCarrito();
            });
        });
        $('carrito').querySelectorAll('.qty-input').forEach(inp => {
            inp.addEventListener('focus', () => inp.select());
            inp.addEventListener('change', () => {
                const idx = +inp.dataset.idx;
                const n = parseNum(inp.value);
                state.carrito[idx].cantidad = (n && n > 0) ? Math.round(n * 1000) / 1000 : 0.001;
                renderCarrito();
            });
        });
        $('carrito').querySelectorAll('[data-del]').forEach(btn => {
            btn.addEventListener('click', () => {
                state.carrito.splice(+btn.dataset.del, 1);
                renderCarrito();
            });
        });

        $('subtotal').textContent = fmt(subtotal());
        $('total').textContent = fmt(total());
        $('btn-cobrar').disabled = ! state.carrito.length || ! state.canSell;
    }

    function medioActual() {
        const id = Number($('medio').value);
        return medios().find(m => Number(m.id) === id);
    }

    function totalConRecargo() {
        const m = medioActual();
        const rec = m ? (Number(m.recargo) || 0) : 0;
        return Math.round(total() * (1 + rec / 100) * 100) / 100;
    }

    function actualizarRecargoPago() {
        const m = medioActual();
        const info = $('recargo-info');
        if (m && m.recargo > 0) {
            info.hidden = false;
            info.textContent = `Recargo ${m.recargo}% → cobrar ${fmt(totalConRecargo())}`;
        } else {
            info.hidden = true;
        }
        $('importe').value = String(totalConRecargo());
        $('pago-total').textContent = fmt(totalConRecargo());
        actualizarVuelto();
    }

    function actualizarVuelto() {
        const recibido = parseNum($('importe').value) || 0;
        const debe = totalConRecargo();
        const el = $('vuelto');
        if (recibido > debe + 0.009) {
            el.hidden = false;
            el.textContent = `Vuelto: ${fmt(recibido - debe)}`;
        } else {
            el.hidden = true;
        }
    }

    function abrirPago() {
        if (state.carrito.some(i => ! (Number(i.precio) > 0))) {
            alert('Hay productos sin precio. Ingresá un importe mayor a 0.');
            return;
        }
        const m = medios().filter(x => x.tipo !== 'qr'); // QR requiere internet/MP
        $('medio').innerHTML = m.map(x =>
            `<option value="${x.id}">${x.nombre}${x.recargo > 0 ? ` (+${x.recargo}%)` : ''}</option>`
        ).join('');
        $('pago-error').hidden = true;
        actualizarRecargoPago();
        $('modal-pago').showModal();
        $('importe').focus();
        $('importe').select();
    }

    async function confirmarVenta(e) {
        e.preventDefault();
        const debe = totalConRecargo();
        const recibido = parseNum($('importe').value);
        if (recibido === null || recibido + 0.009 < debe) {
            $('pago-error').textContent = 'El importe recibido no cubre el total.';
            $('pago-error').hidden = false;
            return;
        }

        const m = medioActual();
        const payload = {
            uuid: crypto.randomUUID(),
            sucursal_id: state.config.sucursal_id,
            cliente_id: $('cliente').value ? Number($('cliente').value) : null,
            descuento: descuento(),
            origen: 'desktop',
            fecha: new Date().toISOString(),
            items: state.carrito.map(i => ({
                producto_id: i.producto_id,
                descripcion: i.nombre,
                cantidad: i.cantidad,
                precio_unitario: i.precio,
                alicuota_iva: i.iva,
            })),
            pagos: [{ medio_pago_id: Number(m.id), importe: debe }],
        };

        try {
            const result = await window.cmoon.submitSale(payload);
            if (result.online) {
                $('ok-title').textContent = 'Venta registrada';
                $('ok-msg').textContent = `Comprobante #${result.numero ?? '—'} · ${fmt(debe)}`;
            } else {
                $('ok-title').textContent = 'Venta guardada sin conexión';
                $('ok-msg').textContent = `Total ${fmt(debe)}. Se sincronizará cuando haya internet.`;
            }
            $('modal-pago').close();
            $('modal-ok').showModal();
            state.carrito = [];
            $('descuento').value = '0';
            if ($('descuento-pct')) $('descuento-pct').value = '0';
            renderCarrito();
            tick();
        } catch (err) {
            $('pago-error').textContent = err.message;
            $('pago-error').hidden = false;
        }
    }

    init().catch(err => alert(err.message));
})();
