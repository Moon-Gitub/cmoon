# Arquitectura

## Stack

| Capa | Tecnología |
|------|------------|
| Backend | Laravel 13, PHP 8.4 |
| **Frontend web** | Blade + Tailwind CSS 4 + Alpine.js + Vite 8 |
| Web server | nginx + PHP-FPM ([serversideup/php](https://serversideup.net/open-source/docker-php/)) |
| Base de datos | MySQL 8.4 |
| Cache / sesiones / colas | Redis 7 |
| Frontend admin | Blade + Tailwind + Alpine.js, Vite |
| POS web | PWA en `/pos` (service worker + localStorage) |
| Caja offline | Electron (`desktop/`) o Android (`mobile/`) → API `/api/desktop/*` |
| Deploy | Docker Compose + Dokploy + Traefik |

## Servicios Docker (`docker-compose.yml`)

| Servicio | Función | Puerto expuesto |
|----------|---------|-----------------|
| `app` | Laravel + nginx | 8080 (host) o solo Traefik en Dokploy |
| `queue` | `php artisan queue:work` | — |
| `scheduler` | `php artisan schedule:work` | — |
| `mysql` | Base CMoon | solo red interna |
| `redis` | Cache, sesión, colas | solo red interna |

Ruta de la app **dentro del contenedor**: `/var/www/html`

## Estructura del repositorio

```
cmoon/
├── app/                 # Código Laravel (modelos, servicios, controllers)
├── config/              # Configuración (moon.php, desktop.php, database.php)
├── database/
│   ├── migrations/
│   └── seeders/         # DatosIniciales, Roles, ProductosEjemplo
├── desktop/             # App Electron (caja offline PC)
├── mobile/              # App Android Capacitor
├── docs/                # Esta documentación
├── resources/
│   ├── views/         # Blade (panel + POS)
│   ├── css/app.css    # Tailwind
│   └── js/app.js      # Alpine.js
├── public/            # sw.js, manifest PWA, build/ (Vite)
├── routes/              # web.php, api.php
├── docker-compose.yml
├── Dockerfile
└── .env.example         # Plantilla de variables
```

## URLs principales (producción de referencia)

| Recurso | URL |
|---------|-----|
| Panel admin | `https://cmoon.aiporvos.com/` |
| Login | `https://cmoon.aiporvos.com/login` |
| POS web | `https://cmoon.aiporvos.com/pos` |
| Health check | `https://cmoon.aiporvos.com/up` |
| API desktop | `https://cmoon.aiporvos.com/api/desktop/...` |

## Conexiones externas

| Sistema | Uso |
|---------|-----|
| BD Moon cobros | Licencias desktop/móvil (`MOON_DB_*`) |
| AFIP WSAA/WSFE | Facturación electrónica (certificados en emisores) |
| Mercado Pago | Pendiente de integración |
