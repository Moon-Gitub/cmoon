# Variables de entorno

Archivo plantilla: `.env.example` en la raíz del repo.

En **Dokploy** no se usa archivo `.env` en disco: cada variable se carga en la pestaña **Environment** del servicio Compose.

## Obligatorias (Docker / Dokploy)

| Variable | Descripción | Ejemplo |
|----------|-------------|---------|
| `APP_KEY` | Clave de cifrado Laravel | `base64:...` (32 bytes random) |
| `APP_URL` | URL pública con https | `https://cmoon.aiporvos.com` |
| `DB_PASSWORD` | Usuario `cmoon` de MySQL | password fuerte |
| `DB_ROOT_PASSWORD` | Root MySQL | password fuerte |
| `REDIS_PASSWORD` | Clave Redis | password fuerte |

## Aplicación

| Variable | Default | Descripción |
|----------|---------|-------------|
| `APP_NAME` | CMoon | Nombre en UI |
| `APP_ENV` | production | `local` en dev |
| `APP_DEBUG` | false | **Nunca true en producción** |
| `APP_PORT` | 8080 | Puerto host (compose local) |
| `ADMIN_PASSWORD` | — | Contraseña del usuario `admin` al seedear |
| `LOG_CHANNEL` | stderr | Logs visibles en `docker compose logs` |
| `LOG_LEVEL` | info | debug solo en desarrollo |

## Base de datos CMoon (servicio `mysql`)

| Variable | Default | Descripción |
|----------|---------|-------------|
| `DB_CONNECTION` | mysql | |
| `DB_HOST` | mysql | Nombre del servicio en compose |
| `DB_PORT` | 3306 | |
| `DB_DATABASE` | cmoon | |
| `DB_USERNAME` | cmoon | |

## Redis (servicio `redis`)

| Variable | Default | Descripción |
|----------|---------|-------------|
| `REDIS_HOST` | redis | Nombre del servicio |
| `REDIS_PORT` | 6379 | |
| `REDIS_CLIENT` | phpredis | |
| `SESSION_DRIVER` | redis | Sesiones web |
| `CACHE_STORE` | redis | Cache Laravel |
| `QUEUE_CONNECTION` | redis | Colas (worker `queue`) |

## Sistema de cobros Moon (licencias desktop/móvil)

| Variable | Default | Descripción |
|----------|---------|-------------|
| `MOON_COBRO_ENABLED` | false | `true` = consulta mora en BD cobros |
| `MOON_DB_HOST` | 107.161.23.11 | Servidor MySQL cobros |
| `MOON_DB_PORT` | 3306 | |
| `MOON_DB_DATABASE` | cobrosposmooncom_db | |
| `MOON_DB_USERNAME` | — | Usuario BD cobros |
| `MOON_DB_PASSWORD` | — | Clave BD cobros |
| `MOON_BLOQUEO_DIA` | 26 | Día del mes que bloquea por mora |

## App de escritorio / móvil offline

| Variable | Default | Descripción |
|----------|---------|-------------|
| `DESKTOP_LICENSE_SECRET` | APP_KEY | Secreto HMAC licencias (`openssl rand -hex 32`) |
| `DESKTOP_OFFLINE_GRACE_DAYS` | 7 | Días que vende offline con abono al día |

## Docker interno

| Variable | Descripción |
|----------|-------------|
| `AUTORUN_ENABLED` | `true` en `app`: migraciones + config cache al arrancar |

## Generadores útiles

```bash
# APP_KEY
echo "base64:$(openssl rand -base64 32)"

# DESKTOP_LICENSE_SECRET
openssl rand -hex 32

# Passwords
openssl rand -base64 24
```
