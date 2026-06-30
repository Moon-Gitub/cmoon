# POSMoon

Sistema de punto de venta completo desarrollado en **Laravel 13**, con integración e-commerce (Tiendanube), facturación electrónica (AFIP), y apps offline para escritorio y móvil.

**Repositorio:** [github.com/Moon-Gitub/cmoon](https://github.com/Moon-Gitub/cmoon)

---

## Características principales

- **Ventas y POS** — Punto de venta táctil, arqueos de caja, múltiples medios de pago
- **Inventario** — Control de stock multi-sucursal, movimientos, alertas de stock bajo
- **Compras** — Gestión de proveedores, cuentas corrientes, retenciones IIBB
- **Clientes** — Base de clientes, cuentas corrientes, historial de compras
- **Integración Tiendanube** — Sync completo de productos, stock, órdenes, carritos abandonados
- **Facturación AFIP** — Factura electrónica A, B, C con homologación
- **Informes** — Ventas, stock, movimientos, retenciones, exportación Excel/PDF
- **Multi-empresa** — Múltiples empresas y sucursales en una instalación
- **Apps offline** — Electron (Windows/Linux) y Android para vender sin conexión

---

## Documentación completa

Toda la guía de instalación, VPS, credenciales, accesos y apps cliente está en:

### **[docs/README.md](./docs/README.md)**

| Tema | Archivo |
|------|---------|
| Arquitectura | [docs/01-arquitectura.md](./docs/01-arquitectura.md) |
| Desarrollo local | [docs/02-desarrollo-local.md](./docs/02-desarrollo-local.md) |
| **VPS / Dokploy** | [docs/03-vps-dokploy.md](./docs/03-vps-dokploy.md) |
| Variables `.env` | [docs/04-variables-entorno.md](./docs/04-variables-entorno.md) |
| **MySQL, Redis, Laravel** | [docs/05-accesos-herramientas.md](./docs/05-accesos-herramientas.md) |
| **Plantilla credenciales** | [docs/06-credenciales-plantilla.md](./docs/06-credenciales-plantilla.md) |
| Productos ejemplo | [docs/07-seeders-datos-ejemplo.md](./docs/07-seeders-datos-ejemplo.md) |
| POS web / PWA | [docs/08-pos-web-pwa.md](./docs/08-pos-web-pwa.md) |
| **Frontend (Vite, Tailwind, Alpine)** | [docs/13-frontend.md](./docs/13-frontend.md) |
| **Agregar módulos Laravel** | [docs/14-guia-desarrollo-modulos.md](./docs/14-guia-desarrollo-modulos.md) |
| App Electron | [docs/09-app-escritorio-electron.md](./docs/09-app-escritorio-electron.md) |
| App Android | [docs/10-app-android.md](./docs/10-app-android.md) |
| API offline | [docs/11-api-desktop.md](./docs/11-api-desktop.md) |
| Mantenimiento | [docs/12-mantenimiento.md](./docs/12-mantenimiento.md) |
| Retenciones IIBB | [docs/15-retenciones-iibb.md](./docs/15-retenciones-iibb.md) |
| **Integración Tiendanube** | [docs/16-integracion-tiendanube.md](./docs/16-integracion-tiendanube.md) |

**Credenciales reales:** copiar plantilla → `CREDENCIALES.local.md` (no se sube a Git).

---

## Stack

| Componente | Tecnología |
|------------|------------|
| Backend | Laravel 13 (PHP 8.4) |
| Web | nginx + PHP-FPM |
| Base de datos | MySQL 8.4 |
| Cache / colas | Redis 7 |
| POS offline | Electron (`desktop/`) + Android (`mobile/`) |
| Deploy | Docker Compose + Dokploy |

Servicios: `app`, `queue`, `scheduler`, `mysql`, `redis`.

---

## Inicio rápido (local)

```bash
cp .env.example .env
docker network create dokploy-network 2>/dev/null || true
docker compose up -d --build
```

→ **http://localhost:8080** — usuario `admin`, clave en `ADMIN_PASSWORD` del `.env`

```bash
docker compose exec app php artisan db:seed --class=ProductosEjemploSeeder --force
```

---

## Producción (referencia)

| Recurso | URL |
|---------|-----|
| Panel | https://cmoon.aiporvos.com |
| POS web | https://cmoon.aiporvos.com/pos |
| API desktop | https://cmoon.aiporvos.com/api/desktop |

Deploy: ver [docs/03-vps-dokploy.md](./docs/03-vps-dokploy.md).

---

## Estructura

```
cmoon/
├── app/           Laravel
├── desktop/       Electron (PC offline)
├── mobile/        Android (APK)
├── docs/          Documentación
├── docker-compose.yml
└── .env.example
```

---

## Integración Tiendanube (opcional)

POSMoon incluye integración completa con Tiendanube (Nuvemshop). **Es totalmente opcional** — el sistema funciona perfectamente sin configurarla.

| Funcionalidad | Descripción |
|---------------|-------------|
| **Productos** | Sync bidireccional, imágenes, variantes |
| **Stock** | Actualización en tiempo real al vender/comprar |
| **Órdenes** | Importación automática vía webhooks |
| **Precios promocionales** | Sync de ofertas y descuentos |
| **Clientes** | Sync bidireccional de datos |
| **Carritos abandonados** | Importar como leads para recuperar ventas |
| **Despacho** | Notificar envío con tracking |
| **Multi-ubicación** | Stock por sucursal/ubicación |
| **Metafields** | Campos personalizados (garantía, marca, etc.) |

### Comandos disponibles

```bash
php artisan tiendanube:sync          # Sync productos y stock
php artisan tiendanube:import-orders # Importar órdenes
php artisan tiendanube:test          # Probar conexión
php artisan tiendanube:sync-prices   # Sync precios promocionales
php artisan tiendanube:import-abandoned # Carritos abandonados
```

Ver documentación completa: [docs/16-integracion-tiendanube.md](./docs/16-integracion-tiendanube.md)

---

## Módulos del sistema

| Módulo | Estado | Descripción |
|--------|--------|-------------|
| Auth & Permisos | ✅ | Usuarios, roles, permisos por empresa |
| Productos | ✅ | Catálogo, categorías, listas de precio |
| Ventas & POS | ✅ | Punto de venta, arqueos, medios de pago |
| Compras | ✅ | Proveedores, cuentas corrientes |
| Retenciones | ✅ | IIBB con exportación SIRCAR |
| Tiendanube | ✅ | Integración e-commerce completa |
| Facturación AFIP | ✅ | Factura electrónica |
| Apps Offline | ✅ | Electron + Android |
| Informes | ✅ | Ventas, stock, exportación Excel/PDF |  
