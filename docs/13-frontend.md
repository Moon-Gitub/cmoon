# Frontend web (Laravel)

Panel administrativo y POS en navegador. **No usa React ni Vue**: es **Blade + Tailwind CSS 4 + Alpine.js**, compilado con **Vite 8**.

---

## Stack

| Capa | Tecnología | Ubicación |
|------|------------|-----------|
| Plantillas | Blade (PHP) | `resources/views/` |
| Estilos | Tailwind CSS 4 | `resources/css/app.css` |
| Interactividad | Alpine.js 3 | `resources/js/app.js` + inline en vistas |
| Bundler | Vite 8 + laravel-vite-plugin | `vite.config.js` |
| Fuente | Instrument Sans (Bunny Fonts) | via plugin Vite |
| PWA POS | Service Worker | `public/sw.js` |
| Manifest | Web App | `public/manifest.webmanifest` |

---

## Estructura de archivos

```
resources/
├── css/
│   └── app.css              # @import tailwindcss + @theme (fuente)
├── js/
│   └── app.js               # Alpine.start() global
└── views/
    ├── layouts/
    │   └── app.blade.php    # Shell panel admin (sidebar, permisos)
    ├── auth/
    │   └── login.blade.php
    ├── pos/
    │   └── index.blade.php  # POS completo (Alpine inline ~500 líneas)
    ├── productos/
    ├── ventas/
    ├── facturacion/
    └── ...                  # Una carpeta por módulo

public/
├── build/                   # Assets compilados (generado por Vite, en Docker build)
├── sw.js                    # Service worker offline POS
├── manifest.webmanifest     # PWA
└── icons/icon.svg

vite.config.js               # Entradas: app.css + app.js
package.json                 # npm run dev | build
```

---

## Desarrollo local (con hot reload)

```bash
# Terminal 1 — Laravel (Docker)
docker compose up -d app

# Terminal 2 — Vite dev server
npm install
npm run dev
```

Vite corre en **http://localhost:5173** y el plugin Laravel inyecta los assets en las vistas Blade.

Si solo usás Docker sin `npm run dev`, los assets se sirven desde `public/build/` generados al **build de la imagen**.

---

## Compilar para producción

```bash
npm run build
```

En **Docker/Dokploy** esto ocurre automáticamente en el **Dockerfile** (etapa `assets` con Node 22). No hace falta correr Vite a mano en el VPS.

Salida: `public/build/assets/*` (hasheados, cacheables).

---

## Panel administrativo

**Layout:** `resources/views/layouts/app.blade.php`

- Sidebar oscuro con navegación por **permisos Spatie** (`@can`, `@canany`)
- Color de acento por empresa: `empresa.color_primario` → CSS variables `--accent`
- Logo: `storage/{empresa.logo_path}`
- Todas las pantallas extienden `@extends('layouts.app')` y usan `@section('contenido')`

**Patrón de interactividad:** Alpine inline donde hace falta (formularios dinámicos):

| Vista | Componente Alpine |
|-------|-------------------|
| `compras/create.blade.php` | `compraForm()` |
| `presupuestos/create.blade.php` | `presupuestoForm()` |
| `facturacion/manual.blade.php` | `facturaManual()` |
| `retenciones/index.blade.php` | cálculo neto/alícuota |

La mayoría de CRUDs son **formularios Blade clásicos** sin JS extra.

---

## POS web (`/pos`)

**Archivo:** `resources/views/pos/index.blade.php`

Vista **standalone** (no usa `layouts/app`): pantalla completa de caja.

### Tecnología

- **Alpine.js** — función `posApp()` definida en `<script>` al final de la misma vista
- **Tailwind** — clases utility en el HTML
- **Fetch API** — catálogo y ventas contra rutas Laravel

### Rutas que consume

| Método | Ruta | Uso |
|--------|------|-----|
| GET | `/pos/catalogo` | JSON productos, clientes, medios, listas |
| POST | `/pos/ventas` | Guardar venta (CSRF + sesión) |

### Funciones del POS

- Búsqueda y sugerencias de productos
- Carrito con cantidades, descuento, cliente, lista de precios
- Múltiples medios de pago
- Integración con caja (sesión abierta)
- **Offline:** cola en `localStorage` (`pos_pendientes`), sync al volver red
- Ticket: `/ventas/{id}/ticket`

### Lector de códigos de barras

Mismo patrón que el POS legacy:

1. Lector USB/BT escribe en el input `#buscador` y manda Enter
2. `agregarPorEnter()` procesa:
   - Código exacto → suma al carrito
   - `cantidad*codigo` → ej. `3*7790895000123`
   - Balanza EAN-13: `2` + PLU (5) + gramos (5) + dígito verificador

### PWA / offline navegador

| Archivo | Rol |
|---------|-----|
| `public/manifest.webmanifest` | Instalable, `start_url: /pos` |
| `public/sw.js` | Cache de `/pos`, `/pos/catalogo`, `/build/*` |

Registrar SW: incluido en `posApp().init()`.

> **Limitación:** sin internet la primera vez **no carga** la app (está en el servidor). Para offline total desde cero usar **Electron** o **Android**.

---

## Login

`resources/views/auth/login.blade.php` — solo `@vite(['resources/css/app.css'])` (sin Alpine en login).

---

## Personalización visual por empresa

En **Empresa** (`/empresa`):

- `color_primario` → sidebar, botones, acentos
- `logo_path` → sidebar e impresiones

El layout inyecta estilos inline que remapean clases `indigo-*` de Tailwind al color de la empresa.

---

## Frontends separados (no son Vite)

Estos clientes tienen **su propio HTML/JS** y no pasan por el build de Laravel:

| Cliente | Carpeta | UI |
|---------|---------|-----|
| Caja PC offline | `desktop/renderer/` | HTML + CSS + JS vanilla |
| Caja móvil | `mobile/www/` | HTML + CSS + JS + Capacitor |

Comparten la **misma API** `/api/desktop/*` y lógica similar de carrito/códigos, pero código independiente del POS web.

Ver [09-app-escritorio-electron.md](./09-app-escritorio-electron.md) y [10-app-android.md](./10-app-android.md).

---

## Convenciones al agregar pantallas

1. Crear vista en `resources/views/{modulo}/`
2. Extender `layouts.app` salvo pantallas full-screen (como POS)
3. Usar clases Tailwind existentes (`rounded-xl`, `bg-slate-900`, etc.)
4. Interactividad ligera → Alpine inline con `x-data`
5. Lógica de negocio → **Controller + Service** en PHP, no en JS
6. Si tocás estilos globales → `resources/css/app.css`
7. Tras cambios CSS/JS → `npm run build` o rebuild Docker

---

## Troubleshooting frontend

| Problema | Solución |
|----------|----------|
| Pantalla sin estilos | Falta `public/build/` → `npm run build` o rebuild imagen |
| `@vite` error en prod | Verificar que Dockerfile copió `public/build` |
| POS no sincroniza offline | Revisar consola; cola en localStorage `pos_pendientes` |
| Cambios JS no se ven | Hard refresh; en dev correr `npm run dev` |
| Service worker viejo | DevTools → Application → Unregister SW; recargar |

---

## Dependencias npm

```json
{
  "devDependencies": {
    "@tailwindcss/vite": "^4",
    "tailwindcss": "^4",
    "vite": "^8",
    "laravel-vite-plugin": "^3"
  },
  "dependencies": {
    "alpinejs": "^3"
  }
}
```

No hay jQuery, Bootstrap ni Livewire en este proyecto.
