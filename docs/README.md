# Documentación POSMoon

Índice de la documentación del proyecto. Repositorio: [github.com/Moon-Gitub/cmoon](https://github.com/Moon-Gitub/cmoon)

| Documento | Contenido |
|-----------|-----------|
| [01-arquitectura.md](./01-arquitectura.md) | Stack, servicios Docker, carpetas del repo |
| [02-desarrollo-local.md](./02-desarrollo-local.md) | Levantar el proyecto en tu PC |
| [03-vps-dokploy.md](./03-vps-dokploy.md) | Deploy en VPS con Dokploy paso a paso |
| [04-variables-entorno.md](./04-variables-entorno.md) | Todas las variables `.env` explicadas |
| [05-accesos-herramientas.md](./05-accesos-herramientas.md) | Laravel, MySQL, Redis, logs, terminal |
| [06-credenciales-plantilla.md](./06-credenciales-plantilla.md) | **Plantilla** para anotar usuarios y claves (copiar a archivo local) |
| [07-seeders-datos-ejemplo.md](./07-seeders-datos-ejemplo.md) | Usuario admin, 20 productos, roles |
| [08-pos-web-pwa.md](./08-pos-web-pwa.md) | POS en navegador (`/pos`) |
| [13-frontend.md](./13-frontend.md) | **Frontend web:** Vite, Tailwind, Alpine, Blade, POS |
| [14-guia-desarrollo-modulos.md](./14-guia-desarrollo-modulos.md) | **Cómo agregar módulos Laravel** (migraciones, permisos, CRUD) |
| [09-app-escritorio-electron.md](./09-app-escritorio-electron.md) | Caja offline Linux/Windows |
| [10-app-android.md](./10-app-android.md) | APK móvil, cámara, lector |
| [11-api-desktop.md](./11-api-desktop.md) | API `/api/desktop/*` y licencias Moon |
| [12-mantenimiento.md](./12-mantenimiento.md) | Migraciones, backups, redeploy, troubleshooting |
| [15-retenciones-iibb.md](./15-retenciones-iibb.md) | **Retenciones IIBB** con exportación SIRCAR |
| [16-integracion-tiendanube.md](./16-integracion-tiendanube.md) | **Integración Tiendanube** (opcional) — sync productos, stock, órdenes |

## Inicio rápido

```bash
# Local
cp .env.example .env
docker network create dokploy-network 2>/dev/null || true
docker compose up -d --build
# → http://localhost:8080  usuario: admin  clave: ver ADMIN_PASSWORD en .env
```

```bash
# VPS (Dokploy) — dentro del contenedor app
cd /var/www/html
php artisan db:seed --class=ProductosEjemploSeeder --force
```

## Credenciales sensibles

**Nunca subir contraseñas reales a GitHub.** Usar:

1. `.env` local (gitignored)
2. Pestaña **Environment** de Dokploy en producción
3. Copia local: `CREDENCIALES.local.md` (ver [06-credenciales-plantilla.md](./06-credenciales-plantilla.md))
