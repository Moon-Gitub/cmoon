# Integración Shopify

Sincronización de productos y órdenes entre **Shopify** y POSMoon (por empresa).

## Requisitos

1. Una tienda Shopify.
2. Una **Custom App** (Admin → Settings → Apps and sales channels → Develop apps) **o** app en [Partners](https://partners.shopify.com/).
3. Scopes mínimos: `read_products`, `write_products` (si hacés push), `read_orders`, `read_inventory`.
4. Queue worker corriendo (`queue` en Docker / `php artisan queue:work`).

## Variables de entorno (opcionales / defaults)

En `.env` (ver también `.env.example`):

```env
SHOPIFY_STORE_DOMAIN=mi-tienda.myshopify.com
SHOPIFY_API_KEY=
SHOPIFY_API_SECRET=
SHOPIFY_ACCESS_TOKEN=
SHOPIFY_WEBHOOK_SECRET=
SHOPIFY_API_VERSION=2025-01
```

Las credenciales **por empresa** se cargan en la UI (`Integraciones → Shopify`). Los valores de `.env` son defaults / fallback de HMAC.

**No hardcodear secrets en el código.**

## Crear Custom App en Shopify Admin

1. En la tienda: **Settings → Apps and sales channels → Develop apps → Create an app**.
2. **Configure Admin API scopes** con los scopes de arriba.
3. **Install app** y copiar el **Admin API access token**.
4. Copiar **API key** y **API secret key** (el secret se usa para verificar webhooks HMAC).
5. En POSMoon: menú **Shopify** → pegar `store_domain` + `access_token` (+ secret) → Guardar.

## Webhooks

URL pública (HTTPS en producción):

```
POST {APP_URL}/webhooks/shopify
```

Local Docker típico: `http://localhost:8089/webhooks/shopify` (Shopify exige HTTPS para webhooks reales; usá un tunnel tipo ngrok/cloudflared en desarrollo).

Topics registrados automáticamente al guardar la integración:

- `orders/create`
- `orders/paid` → job `ImportShopifyOrder` (crea venta `origen=shopify`)
- `products/create` / `products/update` → pull de producto si `auto_create_products`
- `app/uninstalled` → marca integración inactiva

Verificación: header `X-Shopify-Hmac-Sha256` = `base64(hmac_sha256(raw_body, api_secret|webhook_secret))`.

También podés registrar manualmente en Shopify Admin → Settings → Notifications → Webhooks.

## Sincronización de productos

| Dirección | Qué hace | Mapeo |
|-----------|----------|--------|
| **Pull** | Shopify → POSMoon | Variantes → productos locales; SKU → `productos.codigo` |
| **Push** | POSMoon → Shopify | Requiere flag `push_products` |

UI: botones en `/integraciones/shopify`.  
API: `POST /api/v1/shopify/sync/productos` con `{ "direccion": "pull"|"push" }`.

Tablas: `shopify_integraciones`, `shopify_product_maps`, `shopify_logs`.

## API REST + Swagger

Ver [19-api-rest.md](./19-api-rest.md). Documentación interactiva: `/docs/api`.

## Comandos útiles

```bash
# Migraciones
php artisan migrate

# Queue (si no usás el servicio compose "queue")
php artisan queue:work --tries=3

# Probar rutas
php artisan route:list --path=shopify
php artisan route:list --path=api/v1
```

## Archivos clave

- `config/shopify.php`
- `app/Services/ShopifyService.php`
- `app/Http/Controllers/ShopifyController.php`
- `app/Http/Controllers/ShopifyWebhookController.php`
- `app/Jobs/Shopify/*`
- `database/migrations/2026_08_11_210100_create_shopify_tables.php`
