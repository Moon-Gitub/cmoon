const { app, BrowserWindow, ipcMain, dialog } = require('electron');
const path = require('path');
const fs = require('fs');

function crashLog(err) {
    const msg = err?.stack || String(err);
    try {
        const logPath = path.join(app.getPath('userData'), 'crash.log');
        fs.appendFileSync(logPath, `[${new Date().toISOString()}] ${msg}\n\n`);
    } catch { /* ignore */ }
    try {
        dialog.showErrorBox('POSMoon Offline — error al iniciar', msg.slice(0, 1500));
    } catch {
        console.error(msg);
    }
}

process.on('uncaughtException', (err) => {
    crashLog(err);
    app.quit();
});
process.on('unhandledRejection', (err) => {
    crashLog(err);
});

let mainWindow = null;
let dbApi = null;
let licenseApi = null;
let syncApi = null;

function createWindow() {
    mainWindow = new BrowserWindow({
        width: 1280,
        height: 800,
        minWidth: 1024,
        minHeight: 640,
        title: 'POSMoon Offline',
        autoHideMenuBar: true,
        show: false,
        webPreferences: {
            preload: path.join(__dirname, 'preload.js'),
            contextIsolation: true,
            nodeIntegration: false,
        },
    });

    mainWindow.once('ready-to-show', () => mainWindow.show());

    const config = dbApi.getConfig();
    if (! config?.device_token) {
        mainWindow.loadFile(path.join(__dirname, '../renderer/setup.html'));
    } else {
        mainWindow.loadFile(path.join(__dirname, '../renderer/index.html'));
    }
}

function registerIpc() {
    const {
        getConfig, saveConfig, getCatalog, saveCatalog,
        getPendingSales, addPendingSale, removePendingSales,
        saveLicense, getLicense,
    } = dbApi;
    const { verifyLicense, canSellNow } = licenseApi;
    const { activate, refreshLicense, pullCatalog, pushSales, isOnline, isNetworkError } = syncApi;

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
        return verifyLicense(result.license, config.device_token);
    });

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
}

app.whenReady().then(() => {
    try {
        dbApi = require('./db');
        licenseApi = require('./license');
        syncApi = require('./sync');
        dbApi.initDb();
        registerIpc();
        createWindow();
    } catch (err) {
        crashLog(err);
        app.quit();
        return;
    }

    app.on('activate', () => {
        if (BrowserWindow.getAllWindows().length === 0) createWindow();
    });
}).catch((err) => {
    crashLog(err);
    app.quit();
});

app.on('window-all-closed', () => {
    if (process.platform !== 'darwin') app.quit();
});
