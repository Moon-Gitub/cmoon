# POSMoon Offline — App de escritorio (Electron)

Caja para Windows/Linux que vende **sin internet**, con catálogo local (SQLite) y cola de sincronización al servidor POSMoon.

## Qué incluye (v1.1)

- Activación contra el servidor (usuario POS)
- Catálogo local: productos, clientes, listas de precio (incl. al costo), medios de pago
- Venta offline + sync automático al volver internet
- Cantidad decimal (`0.080` / `0,080`), `2*codigo`, balanza EAN-13
- Cliente + lista de precio, descuento, recargo de medio, vuelto
- Licencia Moon con gracia offline (si cobro Moon está habilitado)

No incluye (usar POS web online): facturación AFIP, Mercado Pago QR, multi-pago avanzado.

## Servidor (Dokploy / .env)

```env
DESKTOP_LICENSE_SECRET=   # openssl rand -hex 32
DESKTOP_OFFLINE_GRACE_DAYS=7
MOON_COBRO_ENABLED=false  # true solo si usan cobros Moon
```

```bash
php artisan migrate
```

## Desarrollo

```bash
cd desktop
npm install
npm start
```

## Activación (primera vez)

1. URL: `https://cabanasdelcerro.cluna.ar` (o el dominio del cliente)
2. Usuario / contraseña con permiso `pos.vender`
3. ID Moon: `1` si cobro Moon está desactivado
4. Nombre de la caja

## Empaquetar Windows (instalador)

En una máquina **Windows** (recomendado):

```bash
cd desktop
npm ci
npm run dist:win
```

Desde Linux con Docker (Wine):

```bash
cd desktop
docker run --rm -v "$PWD":/project -w /project electronuserland/builder:wine \
  bash -c "npm ci && npm run dist:win"
```

Salida en `desktop/dist/`:

- `POSMoon-Offline-1.1.0-x64.exe` — instalador NSIS
- Portable `.exe` (sin instalar)

## Comportamiento offline

| Situación | Qué pasa |
|-----------|----------|
| Con internet | Sync catálogo + ventas + licencia |
| Sin internet | Vende con SQLite local; ventas en cola |
| Vuelve internet | Sube cola y renueva licencia |
| Mora Moon (si cobro on) | Bloquea nuevas ventas |

## API

| Método | Ruta |
|--------|------|
| POST | `/api/desktop/activate` |
| GET | `/api/desktop/license` |
| GET | `/api/desktop/catalog` |
| POST | `/api/desktop/sync/ventas` |
| GET | `/api/desktop/status` |
