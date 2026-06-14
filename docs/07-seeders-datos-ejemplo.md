# Seeders y datos de ejemplo

## Seeders incluidos

| Seeder | Qué crea |
|--------|----------|
| `RolesYPermisosSeeder` | Roles (Administrador, Cajero, etc.) y permisos por pantalla |
| `DatosInicialesSeeder` | Empresa, sucursal "Casa Central", usuario `admin` |
| `ProductosEjemploSeeder` | 20 productos argentinos + stock inicial |

## Ejecutar

```bash
# Todo (instalación nueva)
php artisan db:seed --force

# Solo productos ejemplo
php artisan db:seed --class=ProductosEjemploSeeder --force
```

En Docker/Dokploy:

```bash
cd /var/www/html
php artisan db:seed --class=ProductosEjemploSeeder --force
```

El seeder es **idempotente**: actualiza por código de barras sin duplicar.

## Usuario admin

Creado por `DatosInicialesSeeder`:

| Campo | Valor |
|-------|-------|
| Usuario | `admin` |
| Contraseña | variable `ADMIN_PASSWORD` (default dev: `CMoon2026!`) |
| Rol | Administrador |
| Permisos | Todos, incluye `pos.vender` |

## 20 productos ejemplo

Categorías: Bebidas, Almacén, Lácteos, Panadería, Fiambres.

Incluye:

- Coca Cola, Fernet, Quilmes, agua, jugo
- Yerba, arroz, fideos, aceite, azúcar, dulce de leche, Oreo, Lays, café
- Leche, huevos, pan lactal
- **Pesables (balanza):** queso, jamón, salame — códigos PLU `00101`, `00102`, `00103`

Precios orientativos en pesos argentinos. Stock cargado en la sucursal activa.

## Tras cargar productos

1. **Web:** `/productos` o `/informes/stock`
2. **Desktop/móvil:** botón **Sync** para bajar catálogo
3. **POS web:** `/pos` recarga catálogo al abrir con internet

## Crear seeder propio

```bash
php artisan make:seeder MiSeeder
```

Registrar en `database/seeders/DatabaseSeeder.php`.
