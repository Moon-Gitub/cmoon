# Migración desde POS Moon legacy (demonew)

Este documento describe cómo traer **todos los datos de un cliente** desde la base del sistema viejo (demonew / POS Moon PHP) hacia POSMoon, **sin afectar el runtime normal** de la aplicación.

## Dónde vive el código (aislado)

```
cmoon/
├── config/legacy.php                 # Configuración y orden de importadores
├── config/database.php               # Conexión `legacy` (solo lectura)
├── app/LegacyImport/                 # ← Módulo ETL (no se carga en HTTP)
│   ├── LegacyImportOrchestrator.php
│   ├── Importers/                    # Un importador por entidad
│   ├── Mappers/                      # condicion_iva, roles, medios de pago
│   └── Support/                      # IdMap, parsers JSON, contexto
├── app/Console/Commands/
│   └── LegacyImportCommand.php       # php artisan legacy:import
├── database/
│   ├── migrations/..._legacy_import_maps.php
│   └── legacy/mappings/              # Referencia de campos (opcional)
└── docs/15-migracion-legacy.md       # Este archivo
```

**Por qué no afecta al sistema:**

| Aspecto | Decisión |
|---------|----------|
| Ejecución | Solo manual: `php artisan legacy:import` |
| HTTP / API | Nada en `routes/` ni controllers web |
| BD legacy | Conexión separada `legacy`, solo lectura |
| BD POSMoon | Escribe solo cuando ejecutás el comando |
| Idempotencia | Tabla `legacy_import_maps` (mapeo legacy_id → new_id) |
| Producción | `LEGACY_IMPORT_ENABLED=false` por defecto |
| Multi-tenant | Siempre scoped por `--empresa-id` |

## Variables de entorno

Agregar **solo durante la migración** (Dokploy o `.env` local):

```env
LEGACY_IMPORT_ENABLED=true
LEGACY_DB_HOST=127.0.0.1
LEGACY_DB_PORT=3306
LEGACY_DB_DATABASE=nombre_bd_cliente_viejo
LEGACY_DB_USERNAME=readonly_user
LEGACY_DB_PASSWORD=***
LEGACY_EMPRESA_ID=1
```

Recomendación: usuario MySQL **solo SELECT** sobre la BD del cliente.

Después de migrar: `LEGACY_IMPORT_ENABLED=false` y quitar credenciales legacy del entorno de producción.

## Preparación

1. **Backup** de la BD POSMoon destino.
2. Restaurar dump del cliente viejo en un MySQL accesible (puede ser temporal en el VPS).
3. En POSMoon: `php artisan migrate` (incluye tabla `legacy_import_maps`).
4. Crear empresa destino:
   - **Opción A:** empresa vacía con seeders mínimos + `--empresa-id=N`
   - **Opción B:** `--create-empresa` (crea `empresas` desde fila `empresa` legacy)

## Comandos

### Simulación (recomendado primero)

```bash
php artisan legacy:import --empresa-id=2 --dry-run
```

### Importación completa

```bash
php artisan legacy:import --empresa-id=2
```

### Cliente nuevo en POSMoon (crear empresa)

```bash
php artisan legacy:import --create-empresa
```

### Solo algunas entidades

```bash
php artisan legacy:import --empresa-id=2 --only=setup,productos,clientes,ventas
```

### Reimportar forzando (ignora mapeos)

```bash
php artisan legacy:import --empresa-id=2 --force --reset-maps
```

## Orden de importación

| Clave | Contenido |
|-------|-----------|
| `setup` | Empresa, sucursales (almacenes), emisor AFIP, puntos de venta |
| `categorias` | Categorías |
| `listas_precio` | Listas de precio |
| `medios_pago` | Medios de pago |
| `users` | Usuarios + roles Spatie |
| `proveedores` | Proveedores |
| `productos` | Productos + stock por sucursal (`stock`, `stock2`, `stock3`) |
| `combos` | Combos y componentes |
| `clientes` | Clientes |
| `ventas` | Ventas + ítems + pagos |
| `comprobantes` | CAE histórico (`ventas_factura`) |
| `cc_clientes` | Cuenta corriente clientes |
| `cc_proveedores` | Cuenta corriente proveedores |
| `presupuestos` | Presupuestos |
| `compras` | Compras |

## Mapeos importantes

- **Sucursales:** JSON `empresa.almacenes` → `sucursales`; claves `stkProd` (`stock`, `stock2`, …) → stock por sucursal.
- **Condición IVA:** entero AFIP legacy → enum POSMoon (`CondicionIvaMapper`).
- **Ventas:** preferencia `productos_venta`; fallback JSON en `ventas.productos`.
- **Pagos venta:** JSON `metodo_pago` → `venta_pagos`.
- **CC clientes:** tipo 0 = cargo (+), tipo 1 = pago (−).
- **Usuarios:** contraseña bcrypt legacy se conserva; si no, `CMoon2026!`.
- **Emails usuarios:** `{usuario}@legacy-import.local` (cambiar después en panel).

## Qué no migra (v1)

- Movimientos de caja históricos (`cajas` vieja ≠ modelo POSMoon).
- Permisos por pantalla legacy (`permisos_rol`) → usar roles POSMoon.
- Archivos (logo, certificados AFIP, imágenes producto) → copiar manualmente a `storage/`.
- Pedidos móvil legacy (`pedidos` tabla vieja).

## Verificación post-import

1. Conteos: productos, clientes, ventas vs legacy.
2. Stock por sucursal en panel.
3. Saldo cuenta corriente de 2–3 clientes.
4. Login con usuario legacy importado.
5. Desactivar `LEGACY_IMPORT_ENABLED`.

## Clonar cliente en Dokploy

Duplicar el **proyecto/environment** en Dokploy con:

- Misma imagen GitHub `Moon-Gitub/cmoon`
- `.env` distinto: `DB_*`, dominio, `LEGACY_*` solo para la migración
- BD MySQL nueva por cliente

Ver [03-vps-dokploy.md](./03-vps-dokploy.md).
