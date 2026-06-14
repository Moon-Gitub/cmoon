# Accesos y herramientas

## Laravel (panel web)

| Entorno | URL login | Usuario | Contraseña |
|---------|-----------|---------|------------|
| Local | http://localhost:8080/login | `admin` | `ADMIN_PASSWORD` del `.env` |
| Producción | https://cmoon.aiporvos.com/login | `admin` | `ADMIN_PASSWORD` en Dokploy |

Rutas útiles tras login:

| Ruta | Función |
|------|---------|
| `/` | Dashboard |
| `/pos` | Punto de venta (requiere permiso `pos.vender`) |
| `/productos` | Catálogo |
| `/informes/stock` | Stock valorizado |
| `/ventas` | Historial de ventas |
| `/cajas` | Sesiones de caja |
| `/facturacion` | AFIP |
| `/usuarios` | Usuarios y roles |

Health check (sin login): `/up`

---

## Terminal Docker / Dokploy

Siempre entrar al contenedor correcto y directorio Laravel:

```bash
cd /var/www/html
```

| Contenedor | Para qué |
|------------|----------|
| `app` | Artisan, tinker, migraciones |
| `mysql` | Consola MySQL |
| `redis` | redis-cli |
| `queue` | Logs del worker |
| `scheduler` | Logs del cron |

### Dokploy

**Servicio Compose → Terminal → seleccionar contenedor `app`**

### Local

```bash
docker compose exec app bash
docker compose exec mysql bash
docker compose exec redis sh
```

---

## MySQL

**Solo accesible dentro de la red Docker** (no expuesto al internet).

```bash
# Consola interactiva (usuario app)
docker compose exec mysql mysql -u cmoon -p cmoon

# Como root
docker compose exec mysql mysql -u root -p

# Query rápida
docker compose exec mysql mysql -u cmoon -p"$DB_PASSWORD" cmoon -e "SELECT COUNT(*) FROM productos;"
```

| Campo | Valor |
|-------|-------|
| Host (desde contenedores) | `mysql` |
| Puerto | `3306` |
| Base | `cmoon` |
| Usuario app | `cmoon` |
| Usuario root | `root` |
| Claves | `DB_PASSWORD` / `DB_ROOT_PASSWORD` en Environment |

### Tablas principales

`users`, `productos`, `stocks`, `ventas`, `venta_items`, `clientes`, `cajas`, `desktop_installations`, `comprobantes_afip`, etc.

---

## Redis

```bash
docker compose exec redis redis-cli -a "$REDIS_PASSWORD"

# Comandos útiles dentro de redis-cli
PING
KEYS *
INFO memory
```

| Campo | Valor |
|-------|-------|
| Host | `redis` |
| Puerto | `6379` |
| Clave | `REDIS_PASSWORD` |

Usos: sesiones web, cache Laravel, colas `queue:work`.

---

## Artisan (comandos esenciales)

```bash
cd /var/www/html

php artisan migrate --force
php artisan db:seed --force
php artisan db:seed --class=ProductosEjemploSeeder --force
php artisan config:cache
php artisan route:cache
php artisan cache:clear
php artisan queue:restart
php artisan tinker
php artisan route:list
php artisan about
```

---

## Logs

```bash
# Laravel (stderr → Docker logs)
docker compose logs -f app
docker compose logs -f queue
docker compose logs -f scheduler

# Últimas 200 líneas
docker compose logs --tail=200 app
```

Archivos en volumen: `storage/logs/` (dentro del contenedor).

---

## GitHub

| Recurso | URL |
|---------|-----|
| Repositorio | https://github.com/Moon-Gitub/cmoon |
| Clone SSH | `git@github.com:Moon-Gitub/cmoon.git` |

---

## Dokploy

Anotar en [06-credenciales-plantilla.md](./06-credenciales-plantilla.md):

- URL panel Dokploy
- Usuario / contraseña panel
- Nombre del proyecto y servicio Compose

---

## POS viejo (referencia)

| Recurso | URL |
|---------|-----|
| POS legacy PHP | https://newmoon.posmoon.com.ar/ |
| Repo demonew | carpeta local de migración |

---

## API Desktop (sin login web)

Base: `{APP_URL}/api/desktop`

Autenticación: header `Authorization: Bearer {device_token}`

Ver [11-api-desktop.md](./11-api-desktop.md).
