const LS = {
    config: 'cmoon_config',
    catalog: 'cmoon_catalog',
    license: 'cmoon_license',
    pending: 'cmoon_pending_sales',
    pendingPedidos: 'cmoon_pending_pedidos',
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
    return msg.includes('fetch failed')
        || msg.includes('network')
        || msg.includes('failed to fetch')
        || msg.includes('econnrefused')
        || msg.includes('enotfound')
        || msg.includes('etimedout')
        || cause.includes('econnrefused')
        || cause.includes('enotfound');
}

async function request(config, path, { method = 'GET', body = null, auth = true } = {}) {
    const headers = { Accept: 'application/json', 'Content-Type': 'application/json' };
    if (auth && config.device_token) {
        headers.Authorization = `Bearer ${config.device_token}`;
    }

    const res = await fetch(apiUrl(config, path), {
        method,
        headers,
        body: body ? JSON.stringify(body) : undefined,
    });

    const data = await res.json().catch(() => ({}));
    if (! res.ok) {
        throw new Error(data.message || `Error ${res.status}`);
    }
    return data;
}

function getConfig() {
    return readJson(LS.config);
}

function saveConfig(partial) {
    writeJson(LS.config, { ...getConfig(), ...partial });
}

function getCatalog() {
    return readJson(LS.catalog);
}

function saveCatalog(data) {
    writeJson(LS.catalog, data);
}

function getLicense() {
    return localStorage.getItem(LS.license);
}

function saveLicense(token) {
    localStorage.setItem(LS.license, token);
}

function getPendingSales() {
    return readJson(LS.pending, []);
}

function addPendingSale(venta) {
    const list = getPendingSales();
    list.push(venta);
    writeJson(LS.pending, list);
}

function removePendingSales(uuids) {
    const set = new Set(uuids);
    writeJson(LS.pending, getPendingSales().filter(v => ! set.has(v.uuid)));
}

function getPendingPedidos() {
    return readJson(LS.pendingPedidos, []);
}

function addPendingPedido(pedido) {
    const list = getPendingPedidos();
    list.push(pedido);
    writeJson(LS.pendingPedidos, list);
}

function removePendingPedidos(uuids) {
    const set = new Set(uuids);
    writeJson(LS.pendingPedidos, getPendingPedidos().filter(p => ! set.has(p.uuid)));
}

async function activate(datos) {
    const deviceId = datos.device_id || crypto.randomUUID();
    return request({ server_url: datos.server_url }, '/activate', {
        method: 'POST',
        auth: false,
        body: {
            usuario: datos.usuario,
            password: datos.password,
            device_id: deviceId,
            device_name: datos.device_name || 'Caja móvil',
            moon_client_id: parseInt(datos.moon_client_id, 10),
        },
    });
}

async function refreshLicense(config) {
    return request(config, '/license');
}

async function pullCatalog(config) {
    return request(config, '/catalog');
}

async function pushSales(config, ventas) {
    return request(config, '/sync/ventas', { method: 'POST', body: { ventas } });
}

async function pushPedidos(config, pedidos) {
    return request(config, '/sync/pedidos', { method: 'POST', body: { pedidos } });
}

function isActivated() {
    return Boolean(getConfig()?.device_token);
}

function resetApp() {
    Object.values(LS).forEach(k => localStorage.removeItem(k));
}

window.cmoon = {
    getConfig: () => Promise.resolve(getConfig()),
    saveConfig: (partial) => { saveConfig(partial); return Promise.resolve(getConfig()); },
    isActivated: () => Promise.resolve(isActivated()),
    resetApp: () => { resetApp(); return Promise.resolve(); },

    async activate(datos) {
        const result = await activate(datos);
        if (result.catalog) saveCatalog(result.catalog);
        if (result.license) saveLicense(result.license);
        saveConfig({
            server_url: datos.server_url,
            device_token: result.device_token,
            device_id: result.device_id,
            moon_client_id: result.moon_client_id,
            sucursal_id: result.sucursal_id,
            empresa_nombre: result.empresa?.nombre,
            usuario: result.usuario?.name,
            can_sell: result.capabilities?.can_sell ?? true,
            can_pedidos: result.capabilities?.can_pedidos ?? false,
        });
        return result;
    },

    licenseStatus() {
        const config = getConfig();
        const payload = window.cmoonLicense.verifyLicense(getLicense(), config?.device_token);
        return Promise.resolve({
            online: navigator.onLine,
            can_sell: window.cmoonLicense.canSellNow(payload) && (config?.can_sell !== false),
            can_pedidos: config?.can_pedidos === true,
            payload,
            message: payload?.message ?? null,
            blocked: payload?.blocked ?? false,
            valid_until: payload?.valid_until ?? null,
        });
    },

    async refreshLicense() {
        const config = getConfig();
        const result = await refreshLicense(config);
        if (result.license) saveLicense(result.license);
        return window.cmoonLicense.verifyLicense(result.license, config.device_token);
    },

    getCatalog() {
        const cached = getCatalog();
        if (cached) return Promise.resolve(cached);
        return Promise.reject(new Error('No hay catálogo local. Conecte a internet y sincronice.'));
    },

    async syncCatalog() {
        const config = getConfig();
        const catalog = await pullCatalog(config);
        saveCatalog(catalog);
        return catalog;
    },

    pendingCount: () => Promise.resolve(getPendingSales().length + getPendingPedidos().length),

    async submitSale(venta) {
        const config = getConfig();
        const payload = window.cmoonLicense.verifyLicense(getLicense(), config?.device_token);
        if (! window.cmoonLicense.canSellNow(payload)) {
            throw new Error(payload?.message || 'Licencia suspendida.');
        }
        if (config?.can_sell === false) {
            throw new Error('Este usuario no puede registrar ventas.');
        }

        if (navigator.onLine) {
            try {
                const result = await pushSales(config, [venta]);
                if (result.license) saveLicense(result.license);
                const r = result.resultados?.[0];
                if (! r?.ok) throw new Error(r?.error || 'No se pudo registrar la venta');
                return { online: true, numero: r.numero, id: r.id };
            } catch (err) {
                if (! isNetworkError(err)) throw err;
            }
        }

        addPendingSale(venta);
        return { online: false, pendientes: getPendingSales().length };
    },

    async submitPedido(pedido) {
        const config = getConfig();
        if (! config?.can_pedidos) {
            throw new Error('Este usuario no puede tomar pedidos.');
        }

        if (navigator.onLine) {
            try {
                const result = await pushPedidos(config, [pedido]);
                if (result.license) saveLicense(result.license);
                const r = result.resultados?.[0];
                if (! r?.ok) throw new Error(r?.error || 'No se pudo enviar el pedido');
                return { online: true, numero: r.numero, id: r.id, estado: r.estado };
            } catch (err) {
                if (! isNetworkError(err)) throw err;
            }
        }

        addPendingPedido(pedido);
        return { online: false, pendientes: getPendingPedidos().length };
    },

    async syncSales() {
        const config = getConfig();
        const pendientes = getPendingSales();
        if (! pendientes.length) return { sincronizadas: 0 };
        if (! navigator.onLine) throw new Error('Sin conexión para sincronizar.');

        const result = await pushSales(config, pendientes);
        const okUuids = result.resultados.filter(r => r.ok).map(r => r.uuid);
        removePendingSales(okUuids);
        if (result.license) saveLicense(result.license);

        return { sincronizadas: okUuids.length, pendientes: getPendingSales().length, resultados: result.resultados };
    },

    async syncPedidos() {
        const config = getConfig();
        const pendientes = getPendingPedidos();
        if (! pendientes.length) return { sincronizados: 0 };
        if (! navigator.onLine) throw new Error('Sin conexión para sincronizar.');

        const result = await pushPedidos(config, pendientes);
        const okUuids = result.resultados.filter(r => r.ok).map(r => r.uuid);
        removePendingPedidos(okUuids);
        if (result.license) saveLicense(result.license);

        return { sincronizados: okUuids.length, pendientes: getPendingPedidos().length, resultados: result.resultados };
    },

    async syncAll() {
        const ventas = await window.cmoon.syncSales().catch(() => ({ sincronizadas: 0 }));
        const pedidos = await window.cmoon.syncPedidos().catch(() => ({ sincronizados: 0 }));
        return { ventas, pedidos };
    },

    openHome: () => { window.location.href = 'home.html'; },
    openPos: () => { window.location.href = 'pos.html'; },
    openPedido: () => { window.location.href = 'pedido.html'; },
    openSetup: () => { window.location.href = 'setup.html'; },
};
