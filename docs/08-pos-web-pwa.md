# POS web y PWA

## Acceso

| Entorno | URL |
|---------|-----|
| Producción | https://cmoon.aiporvos.com/pos |
| Local | http://localhost:8080/pos |

Requiere login previo y permiso **`pos.vender`**.

## Funciones

- Venta con carrito, medios de pago, cliente
- Caja (abrir/cerrar sesión)
- Catálogo desde API `/pos/catalogo`
- Cola offline en `localStorage` (`pos_pendientes`)
- Service Worker cachea shell y assets (`public/sw.js`)

## Offline en navegador

| Situación | Comportamiento |
|-----------|----------------|
| Con internet | Venta directo al servidor |
| Sin internet (ya cargado) | Usa catálogo en localStorage; ventas en cola |
| Sin internet (primera vez) | **No abre** — la app está en el VPS |

> Para operar **sin internet desde el inicio**, usar app **Electron** o **Android**.

## Lector de código de barras

Igual que el POS legacy: lector USB/BT como teclado → campo de búsqueda + Enter.

Formatos soportados:

- Código exacto del producto
- `cantidad*codigo` (ej. `3*7790895000123`)
- Balanza EAN-13: `2` + PLU (5 dígitos) + gramos (5 dígitos)

## Instalar como PWA

En Chrome/Edge: menú → **Instalar aplicación** (requiere HTTPS en producción).

Manifest: `public/manifest.webmanifest`
