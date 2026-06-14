# CMoon POS — App de escritorio (Electron)

Caja offline con **licencia Moon**: vende sin internet, pero debe conectarse al VPS periódicamente para renovar la licencia. Si no abona el mes, el sistema de cobros bloquea nuevas ventas.

## Requisitos

- Node.js 20+
- Servidor CMoon desplegado con API desktop habilitada

## Configuración del servidor (VPS / Dokploy)

En el `.env` del servidor Laravel:

```env
DESKTOP_LICENSE_SECRET=   # openssl rand -hex 32
DESKTOP_OFFLINE_GRACE_DAYS=7

MOON_COBRO_ENABLED=true
MOON_DB_HOST=107.161.23.11
MOON_DB_DATABASE=cobrosposmooncom_db
MOON_DB_USERNAME=...
MOON_DB_PASSWORD=...
MOON_BLOQUEO_DIA=26
```

Ejecutar migraciones:

```bash
php artisan migrate
```

## Instalar y ejecutar (desarrollo)

```bash
cd desktop
npm install
npm start
```

## Activación (primera vez)

1. URL del servidor: `https://cmoon.aiporvos.com`
2. Usuario y contraseña POS (con permiso `pos.vender`)
3. **ID cliente Moon** (`MOON_CLIENTE_ID` del sistema de cobros)
4. Nombre de la caja

La app descarga catálogo + licencia firmada y queda lista para operar offline.

## Comportamiento

| Situación | Qué pasa |
|-----------|----------|
| Abono al día + internet | Licencia renovada, ventas online u offline |
| Sin internet | Vende usando catálogo local; ventas en cola SQLite |
| Vuelve internet | Sincroniza ventas y renueva licencia automáticamente |
| Mora / bloqueo Moon | Pantalla de suspensión; no permite nuevas ventas |
| Licencia offline vencida (>7 días sin conectar) | Bloqueo hasta reconectar |

## API (servidor)

| Método | Ruta | Uso |
|--------|------|-----|
| POST | `/api/desktop/activate` | Primera vinculación |
| GET | `/api/desktop/license` | Renovar licencia |
| GET | `/api/desktop/catalog` | Descargar catálogo |
| POST | `/api/desktop/sync/ventas` | Subir ventas offline |
| GET | `/api/desktop/status` | Estado dispositivo |

## Empaquetar instalador

```bash
npm run dist
```

Genera `.AppImage` / `.deb` (Linux) o `.exe` (Windows) en `desktop/dist/`.
