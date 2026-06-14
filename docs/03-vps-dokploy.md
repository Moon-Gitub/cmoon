# Instalación en VPS (Dokploy)

## Requisitos del servidor

- VPS con Docker
- [Dokploy](https://dokploy.com) instalado
- Dominio apuntando al VPS (Cloudflare u otro DNS)
- Repo en GitHub: `Moon-Gitub/cmoon`

## Paso 1 — Crear servicio en Dokploy

1. **Projects** → nuevo proyecto o existente
2. **Add Service** → tipo **Compose**
3. Configuración:
   - **Provider:** GitHub
   - **Repository:** `Moon-Gitub/cmoon`
   - **Branch:** `main`
   - **Compose path:** `./docker-compose.yml`
   - **Autodeploy:** On Push (recomendado)

## Paso 2 — Variables de entorno

En la pestaña **Environment** del servicio, cargar (como mínimo):

```env
APP_NAME=CMoon
APP_ENV=production
APP_DEBUG=false
APP_KEY=base64:XXXXXXXX
APP_URL=https://cmoon.aiporvos.com

DB_DATABASE=cmoon
DB_USERNAME=cmoon
DB_PASSWORD=<generar fuerte>
DB_ROOT_PASSWORD=<generar fuerte>

REDIS_PASSWORD=<generar fuerte>

ADMIN_PASSWORD=<clave del usuario admin POS>

# Desktop / móvil (ver 04-variables-entorno.md)
DESKTOP_LICENSE_SECRET=<openssl rand -hex 32>
DESKTOP_OFFLINE_GRACE_DAYS=7
MOON_COBRO_ENABLED=false
```

Generar `APP_KEY`:

```bash
echo "base64:$(openssl rand -base64 32)"
```

Lista completa: [04-variables-entorno.md](./04-variables-entorno.md)

## Paso 3 — Dominio

1. Pestaña **Domains**
2. Agregar dominio (ej. `cmoon.aiporvos.com`)
3. Servicio: **`app`**
4. Puerto interno: **`8080`**
5. HTTPS: Let's Encrypt vía Traefik/Dokploy

## Paso 4 — Deploy

1. **Deploy** / Redeploy
2. Esperar build de imagen (Composer + Vite + PHP)
3. Verificar: `https://tu-dominio/up` → debe responder OK

## Paso 5 — Post-deploy

Terminal del contenedor **`app`** en Dokploy:

```bash
cd /var/www/html

# Migraciones (también corren solas al iniciar si AUTORUN_ENABLED=true)
php artisan migrate --force

# Usuario admin + roles (si es instalación nueva)
php artisan db:seed --force

# 20 productos ejemplo Argentina
php artisan db:seed --class=ProductosEjemploSeeder --force

# Limpiar cache tras cambiar .env
php artisan config:cache
php artisan route:cache
```

## Paso 6 — Activar licencias Moon (opcional)

Si usás caja Electron/Android con cobros:

```env
MOON_COBRO_ENABLED=true
MOON_DB_HOST=107.161.23.11
MOON_DB_DATABASE=cobrosposmooncom_db
MOON_DB_USERNAME=<usuario BD cobros>
MOON_DB_PASSWORD=<clave BD cobros>
MOON_BLOQUEO_DIA=26
```

Redeploy después de cambiar variables.

## Actualizar código

1. `git push` a `main`
2. Dokploy autodeploy (o Redeploy manual)
3. Si hay migraciones nuevas: `php artisan migrate --force` en terminal `app`

## Importar dump del POS viejo

```bash
# Desde el VPS, con acceso al contenedor mysql
docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" cmoon < dump-pos.sql
```

## Red `dokploy-network`

El compose une `app` a la red externa `dokploy-network` para Traefik. Dokploy la crea automáticamente; en local:

```bash
docker network create dokploy-network
```

## Troubleshooting Dokploy

| Problema | Solución |
|----------|----------|
| `Falta APP_KEY en Environment` | Agregar `APP_KEY` en Environment y redeploy |
| `Class ProductosEjemploSeeder does not exist` | Hacer push del código y redeploy |
| `Could not open input file: artisan` | `cd /var/www/html` antes del comando |
| 502 / sin respuesta | Revisar logs del servicio `app`, dominio apuntando a puerto 8080 |
| Sistema suspendido en desktop | `MOON_COBRO_ENABLED=false` para pruebas o regularizar cliente Moon |

Ver también [12-mantenimiento.md](./12-mantenimiento.md).
