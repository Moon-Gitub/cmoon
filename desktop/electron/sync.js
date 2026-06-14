const { net } = require('electron');
const { v4: uuidv4 } = require('uuid');

function isOnline() {
    return net.isOnline();
}

function apiUrl(config, path) {
    const base = (config.server_url || '').replace(/\/$/, '');
    return `${base}/api/desktop${path}`;
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

async function activate(datos) {
    const deviceId = datos.device_id || uuidv4();
    return request({ server_url: datos.server_url }, '/activate', {
        method: 'POST',
        auth: false,
        body: {
            usuario: datos.usuario,
            password: datos.password,
            device_id: deviceId,
            device_name: datos.device_name || 'Caja principal',
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

function isNetworkError(err) {
    const msg = String(err?.message || err || '').toLowerCase();
    const cause = String(err?.cause?.code || err?.cause?.message || '').toLowerCase();

    return msg.includes('fetch failed')
        || msg.includes('network')
        || msg.includes('econnrefused')
        || msg.includes('enotfound')
        || msg.includes('etimedout')
        || msg.includes('timeout')
        || cause.includes('econnrefused')
        || cause.includes('enotfound')
        || cause.includes('etimedout');
}

module.exports = { isOnline, isNetworkError, activate, refreshLicense, pullCatalog, pushSales };
