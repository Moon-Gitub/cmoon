# API REST v1 + OpenAPI (Scramble)

API autenticada con **Laravel Sanctum** (Bearer tokens). Documentación generada con **dedoc/scramble**.

## URLs

| Recurso | Path |
|---------|------|
| Docs UI (Swagger/Elements) | `/docs/api` |
| OpenAPI JSON | `/docs/api.json` |
| Base API | `/api/v1` |

Local Docker: `http://localhost:8089/docs/api`

En `local` la docs está abierta. En otros entornos requiere usuario con `empresa.editar` o rol admin (`Gate::viewApiDocs`).

## Auth

```bash
curl -X POST http://localhost:8089/api/v1/auth/token \
  -H 'Content-Type: application/json' \
  -H 'Accept: application/json' \
  -d '{"usuario":"admin","password":"TU_PASSWORD","device_name":"curl"}'
```

Respuesta: `{ "token": "...", "token_type": "Bearer", "user": {...} }`

Luego:

```bash
export TOKEN=...
curl http://localhost:8089/api/v1/productos \
  -H "Authorization: Bearer $TOKEN" \
  -H 'Accept: application/json'
```

En Scramble UI: botón **Authorize** → pegar el token (scheme Bearer).

## Endpoints

| Método | Path | Descripción |
|--------|------|-------------|
| POST | `/api/v1/auth/token` | Login → token |
| POST | `/api/v1/auth/logout` | Revoca token actual |
| GET | `/api/v1/auth/me` | Usuario actual |
| GET | `/api/v1/productos` | Listado (`q`, `activo`, `per_page`) |
| GET | `/api/v1/productos/{id}` | Detalle |
| GET | `/api/v1/clientes` | Listado |
| GET | `/api/v1/ventas` | Listado (`origen`, `estado`, `desde`, `hasta`) |
| GET | `/api/v1/ventas/{id}` | Detalle con items/pagos |
| POST | `/api/v1/ventas` | Crear venta (requiere permiso POS/ventas) |
| GET | `/api/v1/shopify/status` | Estado integración Shopify |
| POST | `/api/v1/shopify/sync/productos` | Encola pull/push |

La API desktop (`/api/desktop/*`) es independiente (tokens de dispositivo); no aparece en Scramble.

## Librerías

```bash
composer require laravel/sanctum dedoc/scramble
```

## Prueba rápida

```bash
php artisan migrate
php artisan test --filter=ApiV1ProductosTest
```
