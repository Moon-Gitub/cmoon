# API Desktop (offline)

Base URL: `{APP_URL}/api/desktop`

## Endpoints

| Método | Ruta | Auth | Descripción |
|--------|------|------|-------------|
| POST | `/activate` | No | Login + vincular dispositivo |
| GET | `/license` | Bearer | Renovar licencia |
| GET | `/catalog` | Bearer | Catálogo completo |
| GET | `/status` | Bearer | Estado dispositivo |
| POST | `/sync/ventas` | Bearer | Subir ventas offline |

Auth: header `Authorization: Bearer {device_token}`

## Activación (POST /activate)

```json
{
  "usuario": "admin",
  "password": "...",
  "device_id": "uuid",
  "device_name": "Caja 1",
  "moon_client_id": 14
}
```

Respuesta incluye: `device_token`, `license`, `catalog`, `sucursal_id`.

## Venta offline (POST /sync/ventas)

```json
{
  "ventas": [{
    "uuid": "...",
    "sucursal_id": 1,
    "origen": "mobile",
    "fecha": "2026-06-14T12:00:00Z",
    "items": [{ "producto_id": 1, "descripcion": "...", "cantidad": 1, "precio_unitario": 4500, "alicuota_iva": 21 }],
    "pagos": [{ "medio_pago_id": 1, "importe": 4500 }]
  }]
}
```

## Licencia Moon

Firmada con HMAC usando `DESKTOP_LICENSE_SECRET` + `device_token`.

| Variable | Efecto |
|----------|--------|
| `MOON_COBRO_ENABLED=false` | No bloquea por mora (pruebas) |
| `MOON_COBRO_ENABLED=true` | Consulta BD cobros; puede suspender |
| `DESKTOP_OFFLINE_GRACE_DAYS` | Días de venta offline con abono al día |

## Tabla `desktop_installations`

Registra cada caja/móvil vinculado (`device_id`, `moon_client_id`, `token_hash`).

## Código fuente

- Controller: `app/Http/Controllers/Api/DesktopApiController.php`
- Licencias: `app/Services/Desktop/DesktopLicenseService.php`
- Cobros: `app/Services/Moon/MoonCobroService.php`
- Rutas: `routes/api.php`
