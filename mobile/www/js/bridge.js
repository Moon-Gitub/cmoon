const LS = {
    config: 'cmoon_config',
    catalog: 'cmoon_catalog',
    license: 'cmoon_license',
    pending: 'cmoon_pending_sales',
    pendingPedidos: 'cmoon_pending_pedidos',
    pendingCobranzas: 'cmoon_pending_cobranzas',
    pendingVisitas: 'cmoon_pending_visitas',
    pendingEntregas: 'cmoon_pending_entregas',
    rutasCache: 'cmoon_rutas_cache',
    entregasCache: 'cmoon_entregas_cache',
};

function readJson(key, fallback = null) {
    try {
        const raw = localStorage.getItem(key);
        return raw ? JSON.parse(raw) : fallback;
    } catch {
        return fallback;
    }
}

function writeJson(key, value) {
    localStorage.setItem(key, JSON.stringify(value));
}

function apiUrl(config, path) {
    const base = (config.server_url || '').replace(/\/$/, '');
    return `${base}/api/desktop${path}`;
}

function isNetworkError(err) {
    const msg = String(err?.message || err || '').toLowerCase();
    const cause = String(err?.cause?.code || err?.cause?.message || '').toLowerCase();
    return msg.includes('fetch failed') || msg.includes('network') || msg.includes('failed to fetch')
        || msg.includes('econnrefused') || msg.includes('enotfound') || msg.includes('etimedout')
        || cause.includes('econnrefused') || cause.includes('enotfound');
}

async function request(config, path, { method = 'GET', body = null, auth = true } = {}) {
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (auth && config.device_token) headers.Authorization = `Bearer ${config.device_token}`;
    const res = await fetch(apiUrl(config, path), {
        method, headers, body: body ? JSON.stringify(body) : undefined,
    });
    const data = await res.json().catch(() => ({}));
    if (! res.ok) throw new Error(data.message || `Error ${res.status}`);
    return data;
}

function getConfig() { return readJson(LS.config); }
function saveConfig(partial) { writeJson(LS.config, { ...getConfig(), ...partial }); }
function getCatalog() { return readJson(LS.catalog); }
function saveCatalog(data) { writeJson(LS.catalog, data); }
function getLicense() { return localStorage.getItem(LS.license); }
function saveLicense(token) { localStorage.setItem(LS.license, token); }

function queue(name) {
    return {
        all: () => readJson(name, []),
        add: item => writeJson(name, [...readJson(name, []), item]),
        remove: uuids => {
            const set = new Set(uuids);
            writeJson(name, readJson(name, []).filter(x => ! set.has(x.uuid)));
        },
    };
}

const qSales = () => queue(LS.pending);
const qPedidos = () => queue(LS.pendingPedidos);
const qCobranzas = () => queue(LS.pendingCobranzas);
const qVisitas = () => queue(LS.pendingVisitas);
const qEntregas = () => queue(LS.pendingEntregas);

async function pushBatch(config, path, key, items) {
    return request(config, path, { method: 'POST', body: { [key]: items } });
}

async function syncQueue(config, list, path, key, removeFn) {
    if (! list.length) return { ok: 0 };
    if (! navigator.onLine) throw new Error('Sin conexión');
    const result = await pushBatch(config, path, key, list);
    const okUuids = (result.resultados || []).filter(r => r.ok).map(r => r.uuid);
    removeFn(okUuids);
    if (result.license) saveLicense(result.license);
    return { ok: okUuids.length, resultados: result.resultados };
}

async function submitOrQueue(config, onlineFn, queueFn, item) {
    if (navigator.onLine) {
        try {
            const result = await onlineFn();
            if (result.license) saveLicense(result.license);
            const r = result.resultados?.[0];
            if (! r?.ok) throw new Error(r?.error || 'Error al sincronizar');
            return { online: true, ...r };
        } catch (err) {
            if (! isNetworkError(err)) throw err;
        }
    }
    queueFn(item);
    return { online: false };
}

window.cmoon = {
    getConfig: () => Promise.resolve(getConfig()),
    saveConfig: (p) => { saveConfig(p); return Promise.resolve(getConfig()); },
    isActivated: () => Promise.resolve(Boolean(getConfig()?.device_token)),
    resetApp: () => { Object.values(LS).forEach(k => localStorage.removeItem(k)); return Promise.resolve(); },

    async activate(datos) {
        const deviceId = datos.device_id || crypto.randomUUID();
        const result = await request({ server_url: datos.server_url }, '/activate', {
            method: 'POST', auth: false,
            body: { ...datos, device_id: deviceId, moon_client_id: parseInt(datos.moon_client_id, 10), device_name: datos.device_name || 'Caja móvil' },
        });
        if (result.catalog) saveCatalog(result.catalog);
        if (result.license) saveLicense(result.license);
        const cap = result.capabilities || {};
        saveConfig({
            server_url: datos.server_url,
            device_token: result.device_token,
            device_id: result.device_id,
            moon_client_id: result.moon_client_id,
            sucursal_id: result.sucursal_id,
            empresa_nombre: result.empresa?.nombre,
            usuario: result.usuario?.name,
            can_sell: cap.can_sell ?? false,
            can_pedidos: cap.can_pedidos ?? false,
            can_cobranzas: cap.can_cobranzas ?? false,
            can_rutas: cap.can_rutas ?? false,
            can_entregas: cap.can_entregas ?? false,
            can_reportes: cap.can_reportes ?? false,
        });
        return result;
    },

    licenseStatus() {
        const config = getConfig();
        const payload = window.cmoonLicense.verifyLicense(getLicense(), config?.device_token);
        return Promise.resolve({
            can_sell: window.cmoonLicense.canSellNow(payload) && config?.can_sell,
            can_pedidos: config?.can_pedidos === true,
            can_cobranzas: config?.can_cobranzas === true,
            can_rutas: config?.can_rutas === true,
            can_entregas: config?.can_entregas === true,
            can_reportes: config?.can_reportes === true,
            payload, message: payload?.message ?? null, blocked: payload?.blocked ?? false,
        });
    },

    async refreshLicense() {
        const config = getConfig();
        const result = await request(config, '/license');
        if (result.license) saveLicense(result.license);
        return window.cmoonLicense.verifyLicense(result.license, config.device_token);
    },

    getCatalog() {
        const c = getCatalog();
        if (c) return Promise.resolve(c);
        return Promise.reject(new Error('No hay catálogo local. Sincronice con internet.'));
    },

    async syncCatalog() {
        const config = getConfig();
        const catalog = await request(config, '/catalog');
        saveCatalog(catalog);
        return catalog;
    },

    pendingCount: () => Promise.resolve(
        qSales().all().length + qPedidos().all().length + qCobranzas().all().length
        + qVisitas().all().length + qEntregas().all().length
    ),

    async submitSale(venta) {
        const config = getConfig();
        const st = await window.cmoon.licenseStatus();
        if (! st.can_sell) throw new Error(st.message || 'No puede vender.');
        return submitOrQueue(config,
            () => pushBatch(config, '/sync/ventas', 'ventas', [venta]),
            x => qSales().add(x), venta);
    },

    async submitPedido(pedido) {
        if (! getConfig()?.can_pedidos) throw new Error('Sin permiso de pedidos.');
        const config = getConfig();
        return submitOrQueue(config,
            () => pushBatch(config, '/sync/pedidos', 'pedidos', [pedido]),
            x => qPedidos().add(x), pedido);
    },

    async submitCobranza(cobranza) {
        if (! getConfig()?.can_cobranzas) throw new Error('Sin permiso de cobranzas.');
        const config = getConfig();
        return submitOrQueue(config,
            () => pushBatch(config, '/sync/cobranzas', 'cobranzas', [cobranza]),
            x => qCobranzas().add(x), cobranza);
    },

    async submitVisita(visita) {
        if (! getConfig()?.can_rutas) throw new Error('Sin permiso de rutas.');
        const config = getConfig();
        return submitOrQueue(config,
            () => pushBatch(config, '/sync/visitas', 'visitas', [visita]),
            x => qVisitas().add(x), visita);
    },

    async submitEntrega(entrega) {
        if (! getConfig()?.can_entregas) throw new Error('Sin permiso de entregas.');
        const config = getConfig();
        return submitOrQueue(config,
            () => pushBatch(config, '/sync/entregas', 'entregas', [entrega]),
            x => qEntregas().add(x), entrega);
    },

    async syncSales() { return syncQueue(getConfig(), qSales().all(), '/sync/ventas', 'ventas', u => qSales().remove(u)); },
    async syncPedidos() { return syncQueue(getConfig(), qPedidos().all(), '/sync/pedidos', 'pedidos', u => qPedidos().remove(u)); },
    async syncCobranzas() { return syncQueue(getConfig(), qCobranzas().all(), '/sync/cobranzas', 'cobranzas', u => qCobranzas().remove(u)); },
    async syncVisitas() { return syncQueue(getConfig(), qVisitas().all(), '/sync/visitas', 'visitas', u => qVisitas().remove(u)); },
    async syncEntregas() { return syncQueue(getConfig(), qEntregas().all(), '/sync/entregas', 'entregas', u => qEntregas().remove(u)); },

    async syncAll() {
        return {
            ventas: await window.cmoon.syncSales().catch(() => ({ ok: 0 })),
            pedidos: await window.cmoon.syncPedidos().catch(() => ({ ok: 0 })),
            cobranzas: await window.cmoon.syncCobranzas().catch(() => ({ ok: 0 })),
            visitas: await window.cmoon.syncVisitas().catch(() => ({ ok: 0 })),
            entregas: await window.cmoon.syncEntregas().catch(() => ({ ok: 0 })),
        };
    },

    async fetchRutas() {
        const config = getConfig();
        const data = await request(config, '/rutas/mias');
        writeJson(LS.rutasCache, data);
        return data;
    },

    getRutasCache() { return Promise.resolve(readJson(LS.rutasCache, { clientes: [] })); },

    async fetchEntregasPendientes() {
        const config = getConfig();
        const data = await request(config, '/entregas/pendientes');
        writeJson(LS.entregasCache, data);
        return data;
    },

    getEntregasCache() { return Promise.resolve(readJson(LS.entregasCache, { items: [] })); },

    async fetchCliente(id) {
        return request(getConfig(), `/clientes/${id}`);
    },

    async fetchReporte(desde, hasta) {
        const q = new URLSearchParams();
        if (desde) q.set('desde', desde);
        if (hasta) q.set('hasta', hasta);
        return request(getConfig(), `/reportes/vendedor?${q}`);
    },

    openHome: () => { window.location.href = 'home.html'; },
    openPos: () => { window.location.href = 'pos.html'; },
    openPedido: () => { window.location.href = 'pedido.html'; },
    openSetup: () => { window.location.href = 'setup.html'; },
};
