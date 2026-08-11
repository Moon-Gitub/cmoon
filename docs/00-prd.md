# PRD — POSMoon

**Producto:** POSMoon (también referido como CMoon en el código)  
**Repositorio:** [Moon-Gitub/cmoon](https://github.com/Moon-Gitub/cmoon)  
**Documento:** Product Requirements Document  
**Versión:** 1.0  
**Fecha:** 2026-07-30  
**Estado:** Vivo (refleja el producto implementado + backlog conocido)

---

## 1. Resumen ejecutivo

POSMoon es un **sistema de gestión de ventas (POS + backoffice)** multi-empresa para comercios argentinos. Reemplaza / moderniza el POS legacy PHP (demonew / posmoon.com.ar) con:

- Panel web multi-sucursal
- Punto de venta en navegador (PWA)
- Facturación electrónica AFIP
- Caja offline (Electron / Android)
- Migración de datos desde el sistema viejo
- Integraciones opcionales (Tiendanube; Mercado Pago en progreso)

Cada cliente (tenant) corre en su propio despliegue Docker (Dokploy) con su base de datos.

---

## 2. Problema

Los comercios que usan el POS Moon legacy enfrentan:

| Dolor | Impacto |
|-------|---------|
| Código PHP monolítico difícil de evolucionar | Bugs, drift por cliente, deploys riesgosos |
| Poca separación multi-empresa / permisos finos | Mezcla de datos, roles rígidos |
| Offline / móvil limitado | Pérdida de ventas sin internet |
| Facturación e informes acoplados a scripts viejos | Soporte costoso |
| E-commerce desconectado del stock del local | Doble carga de productos |

POSMoon resuelve esto con un stack moderno (Laravel), aislamiento por `empresa_id`, permisos Spatie, API desktop y ETL de migración.

---

## 3. Objetivos del producto

### 3.1 Objetivos de negocio

1. Migrar clientes del POS legacy a POSMoon **sin perder historial** (productos, clientes, ventas, fiscal).
2. Ofrecer un backoffice usable en el día a día (caja, stock, cuentas corrientes, AFIP).
3. Soportar venta online (Tiendanube) alineada al catálogo del local.
4. Reducir costo de soporte: un código base, deploys por contenedor, menos customs por cliente.

### 3.2 Objetivos de usuario

| Rol | Quiere poder… |
|-----|----------------|
| Cajero / vendedor | Cobrar rápido, abrir/cerrar caja, ticket, varios medios de pago |
| Encargado | Ver ventas del día/período, stock, anular con control |
| Administrativo | Facturar AFIP, NC/ND, libro IVA, retenciones IIBB |
| Dueño | Multi-sucursal, usuarios/roles, informes, empresa configurada |
| Vendedor móvil | Presupuestos / rutas con app o flujos móviles |

### 3.3 No-objetivos (por ahora)

- ERP completo (contabilidad general, sueldos, producción)
- Marketplace propio
- Multi-país / multi-moneda avanzada (foco Argentina + ARS + AFIP)
- White-label SaaS self-service con billing automático (hoy: deploy por cliente)

---

## 4. Usuarios y contextos

### Personas

1. **Comercio retail / indumentaria / bicicletería / etc.** — catálogo grande, varias cajas, facturación A/B/C.
2. **Comercio con e-commerce Tiendanube** — stock compartido, órdenes online.
3. **Equipo Moon (operaciones)** — migrar desde demonew, desplegar en Dokploy, soporte.

### Contextos de uso

- Local con internet → panel + `/pos`
- Local con cortes de red → Electron / Android vía API desktop
- Oficina → informes, AFIP, cuentas corrientes
- Calle / preventa → presupuestos y rutas móvil

---

## 5. Alcance funcional (lo que el producto hace hoy)

### 5.1 Core — Administración

| Módulo | Capacidad |
|--------|-----------|
| Auth | Login usuario/email, sesión, perfil (cambio de clave) |
| Empresas | Multi-empresa; datos fiscales, personalización (logo/color) |
| Sucursales | ABM, stock por sucursal |
| Usuarios | ABM, asignación empresa/sucursal |
| Roles y permisos | Spatie (`*.ver`, `*.gestionar`, `pos.vender`, etc.) |

### 5.2 Catálogo

| Módulo | Capacidad |
|--------|-----------|
| Productos | ABM, IVA, precios compra/venta, import CSV, combos |
| Stock | Ajuste por sucursal, informe de stock |
| Categorías | ABM |
| Listas de precio | ABM con % |

### 5.3 Comercial

| Módulo | Capacidad |
|--------|-----------|
| Clientes | ABM, condición IVA, documento |
| Proveedores | ABM |
| Cuentas corrientes | Cliente y proveedor (factura/pago; retención en pago proveedor) |
| Compras | Registro de compras a proveedores |
| Presupuestos | ABM / conversión a flujo de venta |
| Rutas móvil | Asignación vendedor–ruta |

### 5.4 Ventas y caja

| Módulo | Capacidad |
|--------|-----------|
| POS web (`/pos`) | Catálogo, carrito, medios de pago, PWA, códigos balanza |
| Ventas | Listado por período, detalle, anulación, ticket |
| Cajas | Alta de caja por sucursal, abrir/cerrar sesión, movimientos |
| Medios de pago | Efectivo, tarjetas, transferencia, QR, cuenta corriente, etc. |

### 5.5 Fiscal e informes

| Módulo | Capacidad |
|--------|-----------|
| Emisores AFIP | CUIT, certs, entorno homo/prod, puntos de venta |
| Facturación | Emisión desde venta, lote, reintento, manual, NC/ND |
| Comprobantes | Histórico CAE |
| Informes | Ventas, stock, libro IVA |
| Retenciones IIBB | Alta, anulación, export SIRCAR (TXT/ZIP) |

### 5.6 Canales y migraciones

| Módulo | Capacidad |
|--------|-----------|
| Desktop API | Sync/licencias para Electron y Android |
| Tiendanube | Sync productos/stock/órdenes (opcional) |
| Legacy ETL | `legacy:import` desde dump demonew → POSMoon |
| Mercado Pago QR | Endpoints en POS (integración en evolución) |

---

## 6. Requisitos no funcionales

| ID | Requisito | Criterio |
|----|-----------|----------|
| NFR-1 | Multi-tenant por `empresa_id` | Queries de negocio scoped; un usuario no ve otra empresa |
| NFR-2 | Deploy aislado por cliente | Compose propio (Dokploy), volúmenes y DB propios |
| NFR-3 | Disponibilidad operativa | Healthcheck `/up`; queue + scheduler en compose |
| NFR-4 | Seguridad | Auth + permisos; secrets en env Dokploy; no commits de claves |
| NFR-5 | Migrabilidad | Import legacy idempotente (maps); reanudable |
| NFR-6 | UX caja | POS usable en touch / teclado; ticket imprimible |
| NFR-7 | Fiscal AR | AFIP WSFE; homologación y producción |
| NFR-8 | Offline parcial | Desktop/móvil con gracia de licencia configurable |

---

## 7. Arquitectura (resumen de producto)

```
┌─────────────┐   ┌──────────────┐   ┌─────────────┐
│  Panel web  │   │   POS PWA    │   │ Electron /  │
│  (Blade)    │   │   /pos       │   │ Android     │
└──────┬──────┘   └──────┬───────┘   └──────┬──────┘
       │                 │                   │
       └────────────┬────┴───────────────────┘
                    ▼
            Laravel app (+ queue/scheduler)
                    │
         ┌──────────┼──────────┐
         ▼          ▼          ▼
       MySQL      Redis     AFIP / TN / Moon DB
```

- **Stack:** Laravel 13, PHP 8.4, MySQL 8.4, Redis 7, Vite + Tailwind + Alpine  
- **Deploy:** Docker Compose + Dokploy + Traefik  
- Detalle técnico: [01-arquitectura.md](./01-arquitectura.md)

---

## 8. Flujos críticos

### 8.1 Venta en local

1. Usuario con permiso `pos.vender` abre `/pos`
2. (Opcional) Caja abierta en su sucursal
3. Agrega productos → elige medio(s) de pago → confirma
4. Se crea `venta` + ítems + pagos; se descuenta stock
5. (Opcional) Factura AFIP → comprobante con CAE

### 8.2 Cierre de caja

1. Abrir caja con monto inicial  
2. Ventas del turno asociadas a la sesión  
3. Ingresos/egresos manuales  
4. Cierre con arqueo  

### 8.3 Migración de cliente legacy

1. Dump de BD demonew  
2. Carga a `*_legacy` en MySQL del compose  
3. `php artisan legacy:import --create-empresa`  
4. Asignar usuario admin a la empresa migrada  
5. Desactivar flags `LEGACY_*`  
6. Smoke: productos, clientes, ventas, cajas  

Detalle: [15-migracion-legacy.md](./15-migracion-legacy.md)

---

## 9. Métricas de éxito

| Métrica | Señal de éxito |
|---------|----------------|
| Paridad de migración | Conteos clave ≈ legacy (productos, clientes, ventas) |
| Tiempo a primera venta | < 1 día post-deploy con datos migrados |
| Errores AFIP | Tasa de reintento baja; CAE en producción |
| Soporte | Menos tickets de “drift de código” vs demonew |
| Adopción POS | % ventas hechas en `/pos` o desktop vs carga manual |

---

## 10. Backlog / gaps conocidos

Prioridad relativa (producto, no compromiso de sprint):

| Prioridad | Ítem |
|-----------|------|
| Alta | Mercado Pago QR/producción estable en POS |
| Alta | UX post-migración: admin siempre en empresa correcta; cajas por sucursal del tenant |
| Media | Unificar / eliminar empresa seed “Mi Empresa” tras import |
| Media | Paridad total demonew (customs por cliente) |
| Media | Informes más ricos / export Excel |
| Baja | Self-service onboarding SaaS |
| Baja | App móvil preventa feature-complete |

---

## 11. Restricciones y dependencias

- Certificados AFIP por emisor (responsabilidad del cliente / onboarding)
- DNS + HTTPS por dominio de cliente (Cloudflare / Traefik)
- Licencias desktop dependen de BD Moon cobros (`MOON_*`) cuando está habilitado
- Tiendanube requiere app/credenciales del comercio
- Un deploy = un cliente (no multi-tenant compartido en la misma DB)

---

## 12. Criterios de aceptación (producto “listo para un cliente”)

Un despliegue POSMoon se considera **listo** cuando:

- [ ] Login admin funciona en el dominio del cliente  
- [ ] Empresa, sucursal y caja del tenant configuradas  
- [ ] Catálogo visible (migrado o cargado)  
- [ ] Se puede abrir caja y registrar una venta de prueba en `/pos`  
- [ ] (Si aplica) Emisor AFIP en homologación OK  
- [ ] (Si migra) Conteos legacy vs POSMoon validados  
- [ ] Flags `LEGACY_AUTO_MIGRATE` / import desactivados  
- [ ] Backup / acceso Adminer u operación documentada  

---

## 13. Documentación relacionada

| Doc | Uso |
|------|-----|
| [01-arquitectura.md](./01-arquitectura.md) | Stack y servicios |
| [03-vps-dokploy.md](./03-vps-dokploy.md) | Deploy |
| [08-pos-web-pwa.md](./08-pos-web-pwa.md) | POS web |
| [09-app-escritorio-electron.md](./09-app-escritorio-electron.md) | Offline PC |
| [10-app-android.md](./10-app-android.md) | APK |
| [14-guia-desarrollo-modulos.md](./14-guia-desarrollo-modulos.md) | Cómo extender |
| [15-migracion-legacy.md](./15-migracion-legacy.md) | ETL demonew |
| [16-integracion-tiendanube.md](./16-integracion-tiendanube.md) | E-commerce |

---

## 14. Historial

| Versión | Fecha | Cambio |
|---------|-------|--------|
| 1.0 | 2026-07-30 | Primer PRD a partir del código y docs existentes |
