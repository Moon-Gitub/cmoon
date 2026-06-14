const crypto = require('crypto');
const Database = require('better-sqlite3');
const path = require('path');
const { app } = require('electron');

let db = null;

function dbPath() {
    return path.join(app.getPath('userData'), 'cmoon-pos.sqlite');
}

function initDb() {
    db = new Database(dbPath());
    db.pragma('journal_mode = WAL');
    db.exec(`
        CREATE TABLE IF NOT EXISTS config (
            key TEXT PRIMARY KEY,
            value TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS catalog (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            data TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS license (
            id INTEGER PRIMARY KEY CHECK (id = 1),
            token TEXT NOT NULL,
            updated_at TEXT NOT NULL
        );
        CREATE TABLE IF NOT EXISTS pending_sales (
            uuid TEXT PRIMARY KEY,
            payload TEXT NOT NULL,
            created_at TEXT NOT NULL
        );
    `);
}

function getConfig() {
    const rows = db.prepare('SELECT key, value FROM config').all();
    const cfg = {};
    for (const row of rows) {
        try { cfg[row.key] = JSON.parse(row.value); } catch { cfg[row.key] = row.value; }
    }
    return Object.keys(cfg).length ? cfg : null;
}

function saveConfig(partial) {
    const stmt = db.prepare('INSERT INTO config (key, value) VALUES (?, ?) ON CONFLICT(key) DO UPDATE SET value = excluded.value');
    const actual = { ...getConfig(), ...partial };
    for (const [key, value] of Object.entries(actual)) {
        stmt.run(key, typeof value === 'string' ? JSON.stringify(value) : JSON.stringify(value));
    }
}

function getCatalog() {
    const row = db.prepare('SELECT data FROM catalog WHERE id = 1').get();
    return row ? JSON.parse(row.data) : null;
}

function saveCatalog(data) {
    db.prepare('INSERT INTO catalog (id, data, updated_at) VALUES (1, ?, ?) ON CONFLICT(id) DO UPDATE SET data = excluded.data, updated_at = excluded.updated_at')
        .run(JSON.stringify(data), new Date().toISOString());
}

function getLicense() {
    return db.prepare('SELECT token FROM license WHERE id = 1').get()?.token ?? null;
}

function saveLicense(token) {
    db.prepare('INSERT INTO license (id, token, updated_at) VALUES (1, ?, ?) ON CONFLICT(id) DO UPDATE SET token = excluded.token, updated_at = excluded.updated_at')
        .run(token, new Date().toISOString());
}

function getPendingSales() {
    return db.prepare('SELECT uuid, payload FROM pending_sales ORDER BY created_at').all()
        .map(r => JSON.parse(r.payload));
}

function addPendingSale(venta) {
    db.prepare('INSERT OR REPLACE INTO pending_sales (uuid, payload, created_at) VALUES (?, ?, ?)')
        .run(venta.uuid, JSON.stringify(venta), new Date().toISOString());
}

function removePendingSales(uuids) {
    const stmt = db.prepare('DELETE FROM pending_sales WHERE uuid = ?');
    for (const uuid of uuids) stmt.run(uuid);
}

module.exports = {
    initDb, getConfig, saveConfig, getCatalog, saveCatalog,
    getLicense, saveLicense, getPendingSales, addPendingSale, removePendingSales,
};