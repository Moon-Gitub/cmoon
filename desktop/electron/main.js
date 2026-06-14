const { app, BrowserWindow, ipcMain } = require('electron');
const path = require('path');
const { initDb, getConfig, saveConfig, getCatalog, saveCatalog, getPendingSales, addPendingSale, removePendingSales, saveLicense, getLicense } = require('./db');
const { verifyLicense, canSellNow } = require('./license');
const { activate, refreshLicense, pullCatalog, pushSales, isOnline, isNetworkError } = require('./sync');

let mainWindow = null;

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        minWidth: 1024,
        minHeight: 640,
        title: 'CMoon POS',
        autoHideMenuBar: true,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    const config = getConfig();
    if (! config?.device_token) {
        mainWindow.loadFile(path.join(__dirname, '../renderer/setup.html'));
    } else {
        mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));
    }
}

app.whenReady().then(() => {
    initDb();
    createWindow();

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});

// --- IPC: configuración / activación ---
ipcMain.handle('config:get', () => getConfig());

ipcMain.handle('config:save', (_, partial) => {
    saveConfig(partial);
    return getConfig();
});

ipcMain.handle('activate', async (_, datos) => {
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
    });
    return result;
});

// --- IPC: licencia ---
ipcMain.handle('license:status', () => {
    const config = getConfig();
    const license = getLicense();
    const payload = verifyLicense(license, config?.device_token);
    return {
        online: isOnline(),
        can_sell: canSellNow(payload),
        payload,
        message: payload?.message ?? null,
        blocked: payload?.blocked ?? false,
        valid_until: payload?.valid_until ?? null,
    };
});

ipcMain.handle('license:refresh', async () => {
    const config = getConfig();
    if (! isOnline()) throw new Error('Sin conexión al servidor Moon.');
    const result = await refreshLicense(config);
    if (result.license) saveLicense(result.license);
    return verifyLicense(result.license, config.device_id);
});

// --- IPC: catálogo ---
ipcMain.handle('catalog:get', () => {
    const cached = getCatalog();
    if (cached) return cached;
    throw new Error('No hay catálogo local. Conecte a internet y sincronice.');
});

ipcMain.handle('catalog:sync', async () => {
    const config = getConfig();
    if (! isOnline()) throw new Error('Sin conexión.');
    const catalog = await pullCatalog(config);
    saveCatalog(catalog);
    return catalog;
});

// --- IPC: ventas ---
ipcMain.handle('sales:pending-count', () => getPendingSales().length);

ipcMain.handle('sales:submit', async (_, venta) => {
    const config = getConfig();
    const license = getLicense();
    const payload = verifyLicense(license, config?.device_token);
    if (! canSellNow(payload)) {
        throw new Error(payload?.message || 'Licencia suspendida.');
    }

    if (isOnline()) {
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
});

ipcMain.handle('sales:sync', async () => {
    const config = getConfig();
    const pendientes = getPendingSales();
    if (! pendientes.length) return { sincronizadas: 0 };
    if (! isOnline()) throw new Error('Sin conexión para sincronizar.');

    const result = await pushSales(config, pendientes);
    const okUuids = result.resultados.filter(r => r.ok).map(r => r.uuid);
    removePendingSales(okUuids);

    if (result.license) saveLicense(result.license);

    return { sincronizadas: okUuids.length, pendientes: getPendingSales().length, resultados: result.resultados };
});

ipcMain.handle('app:open-pos', () => {
    mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));
});
