(() => {
    const $ = id => document.getElementById(id);
    const fmt = window.cmoonCommon.fmt;
    let items = [];

    async function init() {
        if (! await window.cmoon.isActivated()) return window.location.href = 'setup.html';
        if (! (await window.cmoon.getConfig())?.can_rutas) return window.location.href = 'home.html';
        window.cmoonCommon.bindTopbar(tick);
        await cargar();
    }

    async function cargar() {
        try {
            const data = navigator.onLine ? await window.cmoon.fetchRutas() : await window.cmoon.getRutasCache();
            items = data.clientes || [];
            $('fecha').textContent = `Ruta del ${data.fecha || new Date().toISOString().slice(0,10)}`;
            render();
        } catch {
            const data = await window.cmoon.getRutasCache();
            items = data.clientes || [];
            render();
        }
    }

    function render() {
        $('lista').innerHTML = items.map(c => `
            <div class="list-card static ${c.visitado_hoy ? 'done' : ''}">
                <div><strong>${c.nombre}</strong><span class="muted">${c.ruta_nombre || 'Cartera'}</span></div>
                <p class="muted">${c.domicilio || ''} · ${c.telefono || ''}</p>
                <p>Saldo: ${fmt(c.saldo || 0)}</p>
                ${c.visitado_hoy ? '<span class="pill ok">Visitado</span>' : `<button type="button" class="btn-sm" data-id="${c.id}" data-ruta="${c.ruta_id || ''}">Marcar visita</button>`}
            </div>`).join('') || '<p class="muted">Sin clientes en ruta.</p>';
        $('lista').querySelectorAll('button[data-id]').forEach(b => b.addEventListener('click', () => marcarVisita(b)));
    }

    async function marcarVisita(btn) {
        const geo = await window.cmoonCommon.geo();
        const payload = {
            uuid: crypto.randomUUID(),
            cliente_id: parseInt(btn.dataset.id, 10),
            ruta_id: btn.dataset.ruta ? parseInt(btn.dataset.ruta, 10) : null,
            estado: 'visitada',
            fecha: new Date().toISOString().slice(0, 10),
            lat: geo.lat,
            lng: geo.lng,
            observaciones: 'Visita registrada desde móvil',
        };
        try {
            await window.cmoon.submitVisita(payload);
            btn.closest('.list-card').classList.add('done');
            btn.replaceWith(Object.assign(document.createElement('span'), { className: 'pill ok', textContent: 'Visitado' }));
        } catch (e) { alert(e.message); }
    }

    async function tick() {
        await window.cmoonCommon.refreshStatus(async () => { if (navigator.onLine) await cargar(); });
    }
    init().catch(e => alert(e.message));
})();
