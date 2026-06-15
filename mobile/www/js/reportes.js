(() => {
    const $ = id => document.getElementById(id);
    const fmt = window.cmoonCommon.fmt;

    async function init() {
        if (! await window.cmoon.isActivated()) return window.location.href = 'setup.html';
        if (! (await window.cmoon.getConfig())?.can_reportes) return window.location.href = 'home.html';
        const hoy = new Date().toISOString().slice(0, 10);
        const mes = new Date(); mes.setDate(1);
        $('desde').value = mes.toISOString().slice(0, 10);
        $('hasta').value = hoy;
        $('btn-cargar').addEventListener('click', cargar);
        window.cmoonCommon.bindTopbar(tick);
        cargar();
    }

    async function cargar() {
        if (! navigator.onLine) {
            $('stats').innerHTML = '<p class="muted">Conecte a internet para ver el resumen.</p>';
            return;
        }
        $('stats').innerHTML = '<p class="muted">Cargando…</p>';
        try {
            const d = await window.cmoon.fetchReporte($('desde').value, $('hasta').value);
            $('stats').innerHTML = `
                <div class="stat-card"><span>Pedidos</span><strong>${d.pedidos.cantidad}</strong><small>${fmt(d.pedidos.total)} · ${d.pedidos.aprobados} aprob.</small></div>
                <div class="stat-card"><span>Ventas</span><strong>${d.ventas.cantidad}</strong><small>${fmt(d.ventas.total)}</small></div>
                <div class="stat-card"><span>Cobranzas</span><strong>${d.cobranzas.cantidad}</strong><small>${fmt(d.cobranzas.total)}</small></div>
                <div class="stat-card"><span>Visitas</span><strong>${d.visitas.cantidad}</strong><small>${d.visitas.clientes_unicos} clientes</small></div>
                <div class="stat-card"><span>Entregas</span><strong>${d.entregas.cantidad}</strong></div>
                <p class="hint">Período: ${d.desde} → ${d.hasta}</p>`;
        } catch (e) {
            $('stats').innerHTML = `<p class="error">${e.message}</p>`;
        }
    }

    async function tick() { await window.cmoonCommon.refreshStatus(); }
    init().catch(e => alert(e.message));
})();
