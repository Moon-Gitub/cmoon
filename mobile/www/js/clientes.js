(() => {
    const $ = id => document.getElementById(id);
    const fmt = window.cmoonCommon.fmt;
    let clientes = [], sel = null;

    async function init() {
        if (! await window.cmoon.isActivated()) return window.location.href = 'setup.html';
        const cat = await window.cmoon.getCatalog();
        clientes = cat.clientes || [];
        render();
        $('buscar').addEventListener('input', render);
        window.cmoonCommon.bindTopbar(tick);
        tick();
        const q = new URLSearchParams(location.search).get('id');
        if (q) mostrarDetalle(Number(q));
    }

    function filtrados() {
        const q = $('buscar').value.trim().toLowerCase();
        if (! q) return clientes;
        return clientes.filter(c => (c.nombre + ' ' + (c.documento || '')).toLowerCase().includes(q));
    }

    function render() {
        $('lista').innerHTML = filtrados().map(c => `
            <button type="button" class="list-card" data-id="${c.id}">
                <strong>${c.nombre}</strong>
                <span class="muted">${c.domicilio || c.localidad || ''}</span>
                <span class="saldo-tag ${c.saldo > 0 ? 'debe' : ''}">Saldo: ${fmt(c.saldo || 0)}</span>
            </button>`).join('') || '<p class="muted">Sin clientes.</p>';
        $('lista').querySelectorAll('.list-card').forEach(btn => btn.addEventListener('click', () => mostrarDetalle(Number(btn.dataset.id))));
    }

    async function mostrarDetalle(id) {
        sel = clientes.find(c => Number(c.id) === id);
        $('detalle').hidden = false;
        $('detalle').innerHTML = '<p class="muted">Cargando…</p>';
        try {
            let d = sel;
            if (navigator.onLine) d = await window.cmoon.fetchCliente(id);
            $('detalle').innerHTML = `
                <h3>${d.nombre}</h3>
                <p>${d.telefono || ''} · ${d.domicilio || ''}</p>
                <p><strong>Saldo:</strong> ${fmt(d.saldo || 0)} · Límite: ${d.limite_credito ? fmt(d.limite_credito) : '—'}</p>
                <div class="card-actions">
                    <a href="pedido.html?cliente=${id}" class="btn-sm">Pedido</a>
                    <a href="cobranza.html?cliente=${id}" class="btn-sm">Cobrar</a>
                </div>
                <h4>Ventas recientes</h4>
                ${(d.ventas_recientes || []).map(v => `<div class="cart-row"><span>#${String(v.numero).padStart(6,'0')}</span><span>${fmt(v.total)}</span></div>`).join('') || '<p class="muted">Sin ventas</p>'}
                <h4>Pedidos recientes</h4>
                ${(d.presupuestos_recientes || []).map(p => `<div class="cart-row"><span>#${String(p.numero).padStart(6,'0')} ${p.estado}</span><span>${fmt(p.total)}</span></div>`).join('') || '<p class="muted">Sin pedidos</p>'}
            `;
        } catch (e) {
            $('detalle').innerHTML = `<p class="error">${e.message}</p>`;
        }
    }

    async function tick() { await window.cmoonCommon.refreshStatus(); }
    init().catch(e => alert(e.message));
})();
