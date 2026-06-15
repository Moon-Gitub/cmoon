(() => {
    async function init() {
        if (! await window.cmoon.isActivated()) {
            window.location.href = 'setup.html';
            return;
        }

        const config = await window.cmoon.getConfig();
        $('empresa').textContent = config?.empresa_nombre || 'CMoon';
        $('usuario').textContent = config?.usuario || 'Usuario';

        const st = await window.cmoon.licenseStatus();
        if (st.blocked && ! st.can_pedidos) {
            alert(st.message || 'Licencia suspendida.');
        }

        if (config?.can_sell !== false && st.can_sell) {
            $('btn-pos').hidden = false;
        }
        if (config?.can_pedidos) {
            $('btn-pedido').hidden = false;
        }

        if ($('btn-pos').hidden && $('btn-pedido').hidden) {
            alert('Este usuario no tiene permisos para usar la app.');
        }

        $('btn-sync').addEventListener('click', tick);
        $('btn-setup').addEventListener('click', () => window.cmoon.openSetup());
        setInterval(tick, 30000);
        window.addEventListener('online', tick);
        window.addEventListener('offline', () => updateOnlinePill(false));
        tick();
    }

    const $ = (id) => document.getElementById(id);

    function updateOnlinePill(serverOk) {
        $('online').textContent = serverOk ? 'En línea' : 'Sin conexión';
        $('online').className = 'pill ' + (serverOk ? 'ok' : 'off');
    }

    async function tick() {
        let serverOk = false;
        try {
            if (navigator.onLine) {
                await window.cmoon.refreshLicense();
                await window.cmoon.syncCatalog().catch(() => {});
                await window.cmoon.syncAll().catch(() => {});
                serverOk = true;
            }
        } catch { /* sin servidor */ }
        updateOnlinePill(serverOk);
        const n = await window.cmoon.pendingCount();
        $('pending').hidden = n === 0;
        if (n) $('pending').textContent = `${n} pend.`;
    }

    init().catch(err => alert(err.message));
})();
