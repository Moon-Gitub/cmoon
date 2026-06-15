(() => {
    const $ = id => document.getElementById(id);
    async function init() {
        if (! await window.cmoon.isActivated()) return window.location.href = 'setup.html';
        const config = await window.cmoon.getConfig();
        $('empresa').textContent = config?.empresa_nombre || 'POSMoon';
        $('usuario').textContent = config?.usuario || 'Usuario';
        const st = await window.cmoon.licenseStatus();
        const map = [
            ['btn-pos', st.can_sell],
            ['btn-pedido', st.can_pedidos],
            ['btn-clientes', st.can_pedidos || st.can_cobranzas || st.can_rutas],
            ['btn-cobranza', st.can_cobranzas],
            ['btn-rutas', st.can_rutas],
            ['btn-entregas', st.can_entregas],
            ['btn-reportes', st.can_reportes],
        ];
        map.forEach(([id, ok]) => { if (ok) $(id).hidden = false; });
        $('btn-sync').addEventListener('click', tick);
        $('btn-setup').addEventListener('click', () => window.cmoon.openSetup());
        tick();
        setInterval(tick, 30000);
    }
    async function tick() {
        let ok = false;
        try {
            if (navigator.onLine) {
                await window.cmoon.refreshLicense();
                await window.cmoon.syncCatalog().catch(() => {});
                await window.cmoon.syncAll().catch(() => {});
                ok = true;
            }
        } catch { /* */ }
        $('online').textContent = ok ? 'En línea' : 'Sin conexión';
        $('online').className = 'pill ' + (ok ? 'ok' : 'off');
        const n = await window.cmoon.pendingCount();
        $('pending').hidden = ! n;
        if (n) $('pending').textContent = `${n} pend.`;
    }
    init().catch(e => alert(e.message));
})();
