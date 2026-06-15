# App de escritorio (Electron)

Carpeta: `desktop/`

Caja offline con licencia Moon. Usa API `/api/desktop/*`.

## Requisitos

- Node.js 20+
- Servidor POSMoon desplegado

## Desarrollo

```bash
cd desktop
npm install
npm start
```

En Linux puede requerir:

```bash
npm start -- --no-sandbox
# o
ELECTRON_DISABLE_SANDBOX=1 npm start
```

## Activación (primera vez)

1. URL: `https://cmoon.aiporvos.com` (sin dominio inventado)
2. Usuario / contraseña POS (`admin` + `ADMIN_PASSWORD`)
3. ID cliente Moon (sistema de cobros)
4. Nombre de la caja

Datos locales: `~/.config/cmoon-desktop/cmoon-pos.sqlite`

## Reset / reactivar

```bash
rm ~/.config/cmoon-desktop/cmoon-pos.sqlite
cd desktop && npm start
```

## Empaquetar

```bash
cd desktop
npm run dist
```

| SO | Salida |
|----|--------|
| Linux | `dist/POSMoon-1.0.0.AppImage`, `.deb` |
| Windows | `.exe` (compilar en Windows) |

Ejecutar AppImage en Linux:

```bash
./POSMoon-1.0.0.AppImage --no-sandbox
```

## Lector de códigos

- USB/Bluetooth (modo teclado): campo búsqueda + Enter
- Balanza EAN-13 `2XXXXXXXXXXXXX`
- `cantidad*codigo`

## Configuración servidor

Ver [04-variables-entorno.md](./04-variables-entorno.md) — `DESKTOP_LICENSE_SECRET`, `MOON_COBRO_*`.

## Comportamiento offline

Ver [11-api-desktop.md](./11-api-desktop.md).
