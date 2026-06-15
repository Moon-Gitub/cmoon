# POSMoon

Migración del POS Moon a **Laravel 13**, dockerizado y listo para desplegar en un VPS con **Dokploy**.

**Repositorio:** [github.com/Moon-Gitub/cmoon](https://github.com/Moon-Gitub/cmoon)

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

## Fases del proyecto

1. Auth, usuarios, permisos, empresa  
2. Productos, categorías, listas  
3. Ventas, caja, clientes  
4. Compras, retenciones, informes  
5. POS offline + API desktop  
6. Facturación AFIP  
7. Apps Electron y Android  
