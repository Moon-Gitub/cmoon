# Plantilla de credenciales

> **IMPORTANTE:** Este archivo es una **plantilla**. Copialo a `CREDENCIALES.local.md` en la raíz del proyecto (ese archivo está en `.gitignore` y **no se sube a GitHub**).

```bash
cp docs/06-credenciales-plantilla.md CREDENCIALES.local.md
# Completar CREDENCIALES.local.md con valores reales
```

---

## GitHub

| Campo | Valor |
|-------|-------|
| Organización | Moon-Gitub |
| Repositorio | cmoon |
| URL | https://github.com/Moon-Gitub/cmoon |
| Rama producción | main |

---

## Dokploy (panel VPS)

| Campo | Valor |
|-------|-------|
| URL panel | ___________________________ |
| Usuario | ___________________________ |
| Contraseña | ___________________________ |
| Proyecto | ___________________________ |
| Servicio Compose | ___________________________ |

---

## Aplicación CMoon (producción)

| Campo | Valor |
|-------|-------|
| URL pública | https://cmoon.aiporvos.com |
| APP_URL | https://cmoon.aiporvos.com |
| APP_KEY | ___________________________ |

### Usuario administrador POS

| Campo | Valor |
|-------|-------|
| Usuario | admin |
| Contraseña | ___________________________ (ADMIN_PASSWORD) |
| Email | admin@cmoon.local |

---

## MySQL (contenedor `mysql`)

| Campo | Valor |
|-------|-------|
| Host interno | mysql |
| Puerto | 3306 |
| Base | cmoon |
| Usuario app | cmoon |
| DB_PASSWORD | ___________________________ |
| Root | root |
| DB_ROOT_PASSWORD | ___________________________ |

---

## Redis (contenedor `redis`)

| Campo | Valor |
|-------|-------|
| Host interno | redis |
| Puerto | 6379 |
| REDIS_PASSWORD | ___________________________ |

---

## Sistema de cobros Moon (BD externa)

| Campo | Valor |
|-------|-------|
| MOON_COBRO_ENABLED | false / true |
| MOON_DB_HOST | 107.161.23.11 |
| MOON_DB_DATABASE | cobrosposmooncom_db |
| MOON_DB_USERNAME | ___________________________ |
| MOON_DB_PASSWORD | ___________________________ |
| ID cliente Moon (ejemplo) | 14 / 20 |

---

## Licencias desktop / móvil

| Campo | Valor |
|-------|-------|
| DESKTOP_LICENSE_SECRET | ___________________________ |
| DESKTOP_OFFLINE_GRACE_DAYS | 7 |

---

## Cloudflare / DNS (si aplica)

| Campo | Valor |
|-------|-------|
| Dominio | cmoon.aiporvos.com |
| Registro A/CNAME | ___________________________ |
| SSL | Full (strict) vía Dokploy/Traefik |

---

## AFIP (facturación electrónica)

| Campo | Valor |
|-------|-------|
| CUIT emisor | ___________________________ |
| Certificado .crt | subido en /emisores |
| Clave privada .key | subido en /emisores |
| Punto de venta | ___________________________ |

---

## VPS SSH (opcional)

| Campo | Valor |
|-------|-------|
| IP | ___________________________ |
| Usuario SSH | ___________________________ |
| Clave / acceso | ___________________________ |

---

## Apps cliente

| App | Ubicación / notas |
|-----|-------------------|
| Electron Linux | `desktop/dist/CMoon POS-1.0.0.AppImage --no-sandbox` |
| Android APK | `mobile/dist/CMoon-POS-1.0.0-debug.apk` |
| URL activación apps | https://cmoon.aiporvos.com |

---

## Desarrollo local

| Campo | Valor |
|-------|-------|
| URL | http://localhost:8080 |
| .env | copia de .env.example |
| ADMIN_PASSWORD local | ___________________________ |

---

*Última actualización: completar fecha al guardar credenciales reales.*
