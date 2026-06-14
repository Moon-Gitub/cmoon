# Desarrollo local

## Requisitos

- Docker + Docker Compose
- Git
- (Opcional) Node 20+ para compilar assets sin Docker
- (Opcional) Node 20+ para `desktop/` y `mobile/`

## Primer arranque

```bash
git clone git@github.com:Moon-Gitub/cmoon.git
cd cmoon

cp .env.example .env
# Editar .env: APP_KEY, DB_PASSWORD, DB_ROOT_PASSWORD, REDIS_PASSWORD, ADMIN_PASSWORD

# Red externa que usa el compose (Traefik/Dokploy)
docker network create dokploy-network 2>/dev/null || true

docker compose up -d --build
```

App: **http://localhost:8080**

Al iniciar, `AUTORUN_ENABLED=true` ejecuta migraciones y cache de config automáticamente.

## Credenciales iniciales (desarrollo)

| Campo | Valor por defecto |
|-------|---------------------|
| Usuario | `admin` |
| Contraseña | valor de `ADMIN_PASSWORD` en `.env` (ej. `CMoon2026!`) |
| Email | `admin@cmoon.local` |

Generar `APP_KEY`:

```bash
echo "base64:$(openssl rand -base64 32)"
```

## Comandos frecuentes

```bash
# Logs
docker compose logs -f app

# Artisan
docker compose exec app php artisan migrate
docker compose exec app php artisan db:seed --force
docker compose exec app php artisan db:seed --class=ProductosEjemploSeeder --force
docker compose exec app php artisan tinker
docker compose exec app php artisan route:list

# MySQL
docker compose exec mysql mysql -u cmoon -p cmoon

# Redis
docker compose exec redis redis-cli -a "$REDIS_PASSWORD" ping

# Rebuild tras cambios de código
docker compose up -d --build app queue scheduler
```

## Assets frontend (Vite)

Se compilan en el **build de la imagen Docker**. Para desarrollo con hot reload:

```bash
npm install
npm run dev
```

## Datos de ejemplo

```bash
docker compose exec app php artisan db:seed --class=ProductosEjemploSeeder --force
```

Ver [07-seeders-datos-ejemplo.md](./07-seeders-datos-ejemplo.md).

## Apps cliente offline

- Escritorio: [09-app-escritorio-electron.md](./09-app-escritorio-electron.md) — URL servidor local: `http://localhost:8080` o túnel
- Android: [10-app-android.md](./10-app-android.md)
