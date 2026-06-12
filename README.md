# CMoon POS

Migración del POS Moon a **Laravel 13**, dockerizado y listo para desplegar en un VPS con **Dokploy**.

## Stack

| Componente | Tecnología |
|------------|-----------|
| Backend | Laravel 13 (PHP 8.4) |
| Servidor web | nginx + PHP-FPM en un solo contenedor ([serversideup/php](https://serversideup.net/open-source/docker-php/)) |
| Base de datos | MySQL 8.4 LTS (compatible con los dumps del POS actual) |
| Cache / sesiones / colas | Redis 7 |
| Assets | Vite (se compilan en el build de la imagen) |

El `docker-compose.yml` levanta 5 servicios: `app` (web), `queue` (worker de colas), `scheduler` (tareas programadas), `mysql` y `redis`.

## Desarrollo local

```bash
cp .env.example .env        # ya existe uno configurado en este repo local
docker compose up -d --build
```

La app queda en [http://localhost:8080](http://localhost:8080). Las migraciones corren solas al iniciar (`AUTORUN_ENABLED=true`).

Comandos útiles:

```bash
docker compose exec app php artisan migrate      # migraciones a mano
docker compose exec app php artisan tinker       # consola
docker compose logs -f app                       # logs
```

## Deploy en Dokploy

1. Subir este repo a GitHub.
2. En Dokploy crear un servicio **Compose**:
   - Provider: GitHub, rama `main`
   - Compose Path: `./docker-compose.yml`
   - Trigger: On Push (autodeploy)
3. En la pestaña **Environment** cargar como mínimo:

```env
APP_KEY=base64:...        # generar con: echo "base64:$(openssl rand -base64 32)"
APP_URL=https://pos.tudominio.com
DB_PASSWORD=<password fuerte>
DB_ROOT_PASSWORD=<password fuerte>
REDIS_PASSWORD=<password fuerte>
```

4. En la pestaña **Domains** apuntar el dominio al servicio `app`, puerto interno `8080`.
   - Si se usa dominio por Dokploy/Traefik, se puede quitar el bloque `ports` del servicio `app` para no ocupar el puerto del host.
5. Deploy.

MySQL y Redis no exponen puertos al host: solo son accesibles dentro de la red del compose.

## Importar datos del POS actual

La base es MySQL, igual que el sistema viejo, así que un dump se importa directo:

```bash
docker compose exec -T mysql mysql -u root -p"$DB_ROOT_PASSWORD" cmoon < dump-pos.sql
```

## Estructura del proyecto de migración

Plan por fases (ver análisis en el repo del POS actual):

1. **Fase 1** — Auth, usuarios, permisos por pantalla, empresa
2. **Fase 2** — Productos, categorías, combos, listas de precio
3. **Fase 3** — Ventas, caja, presupuestos, clientes y ctas. ctes.
4. **Fase 4** — Compras, proveedores, retenciones IIBB, informes
5. **Fase 5** — API para POS offline
6. **Fase 6** — Facturación AFIP, libro IVA, PDFs
7. **Fase 7** — Mercado Pago y cobro Moon
