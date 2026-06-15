window.cmoonCommon = {
    fmt(n) {
        return '$ ' + Number(n || 0).toLocaleString('es-AR', { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    },
    qs(id) { return document.getElementById(id); },
    async geo() {
        return new Promise((resolve) => {
            if (! navigator.geolocation) return resolve({ lat: null, lng: null });
            navigator.geolocation.getCurrentPosition(
                p => resolve({ lat: p.coords.latitude, lng: p.coords.longitude }),
                () => resolve({ lat: null, lng: null }),
                { enableHighAccuracy: true, timeout: 8000 },
            );
        });
    },
    bindTopbar(tick) {
        window.cmoonCommon.qs('btn-home')?.addEventListener('click', () => window.cmoon.openHome());
        window.cmoonCommon.qs('btn-sync')?.addEventListener('click', tick);
        setInterval(tick, 30000);
        window.addEventListener('online', tick);
        window.addEventListener('offline', () => {
            const el = window.cmoonCommon.qs('online');
            if (el) { el.textContent = 'Sin conexión'; el.className = 'pill off'; }
        });
    },
    async refreshStatus(tickExtra) {
        let ok = false;
        try {
            if (navigator.onLine) {
                await window.cmoon.refreshLicense();
                await window.cmoon.syncCatalog().catch(() => {});
                await window.cmoon.syncAll().catch(() => {});
                if (tickExtra) await tickExtra();
                ok = true;
            }
        } catch { /* offline */ }
        const el = window.cmoonCommon.qs('online');
        if (el) {
            el.textContent = ok ? 'En línea' : 'Sin conexión';
            el.className = 'pill ' + (ok ? 'ok' : 'off');
        }
        const n = await window.cmoon.pendingCount();
        const p = window.cmoonCommon.qs('pending');
        if (p) { p.hidden = n === 0; if (n) p.textContent = `${n} pend.`; }
    },
};
