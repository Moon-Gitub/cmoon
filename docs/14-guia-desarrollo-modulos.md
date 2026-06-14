# Guía: cómo está hecho CMoon y cómo agregar módulos

Esta guía explica **qué se creó en cada capa** del proyecto Laravel y el **paso a paso** para sumar pantallas, APIs o funcionalidad nueva siguiendo las mismas convenciones.

---

## Cómo se construyó el proyecto (mapa por fases)

Cada fase = migraciones + permisos + modelos + controllers + vistas + (a veces) servicios.

| Fase | Migraciones clave | Qué agregó |
|------|-------------------|------------|
| **Base** | `000001_users`, `000002_empresas`, `000004_seed_datos` | Auth, empresas, sucursales, usuario `admin`, Spatie roles |
| **Catálogo** | `100000_create_catalogo`, `100001_seed_permisos_catalogo` | Productos, categorías, stock, listas de precio |
| **Comercial** | `110000_create_comercial`, `110001_seed_comercial` | Clientes, medios de pago, proveedores |
| **Ventas** | `120000_create_ventas`, `120001_seed_permisos_ventas` | POS web, ventas, cajas |
| **AFIP** | `130000_create_afip`, `130001_seed_permisos_facturacion` | Facturación electrónica, emisores |
| **Informes** | `130002_seed_permiso_informes` | Informes ventas/stock/libro IVA |
| **Fiscal extra** | `140000`, `140001` | NC/ND manual, retenciones IIBB |
| **Compras** | `150000`, `150001` | Compras, presupuestos, combos |
| **Personalización** | `150002_add_personalizacion_empresas` | Logo y color por empresa |
| **Desktop API** | `2026_06_13_create_desktop_installations` | Licencias caja offline |
| **Datos demo** | Seeder `ProductosEjemploSeeder` | 20 productos argentinos |

---

## Capas del proyecto (qué hace cada una)

```
Request HTTP
    ↓
routes/web.php  o  routes/api.php     ← URL + middleware + permisos
    ↓
Controller                           ← valida, autoriza, devuelve vista/JSON
    ↓
Service (opcional)                   ← lógica de negocio reutilizable
    ↓
Model + Eloquent                     ← BD, relaciones, scope empresa
    ↓
Migration                            ← estructura de tablas
    ↓
View Blade (+ Alpine si hace falta)   ← HTML del panel o POS
```

---

## Checklist: agregar un módulo nuevo (ej. "Promociones")

### 1. Migración (tabla)

```bash
docker compose exec app php artisan make:migration create_promociones_table
```

Editar `database/migrations/xxxx_create_promociones_table.php`:

```php
Schema::create('promociones', function (Blueprint $table) {
    $table->id();
    $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
    $table->string('nombre');
    $table->decimal('descuento_pct', 5, 2);
    $table->boolean('activa')->default(true);
    $table->timestamps();
});
```

Correr:

```bash
docker compose exec app php artisan migrate
```

### 2. Modelo

```bash
docker compose exec app php artisan make:model Promocion
```

En `app/Models/Promocion.php`:

```php
use App\Models\Concerns\PerteneceAEmpresa;

class Promocion extends Model
{
    use PerteneceAEmpresa;  // filtra por empresa_id del usuario logueado

    protected $fillable = ['empresa_id', 'nombre', 'descuento_pct', 'activa'];
}
```

> Usá `PerteneceAEmpresa` en todo lo que pertenezca a una empresa (productos, clientes, ventas, etc.).

### 3. Permisos (Spatie)

Crear migración de permisos (como las existentes):

```bash
php artisan make:migration seed_permisos_promociones
```

```php
private const PERMISOS = [
    'promociones.ver',
    'promociones.gestionar',
];

public function up(): void
{
    app(PermissionRegistrar::class)->forgetCachedPermissions();
    foreach (self::PERMISOS as $p) {
        Permission::firstOrCreate(['name' => $p, 'guard_name' => 'web']);
    }
    Role::findByName('Administrador')->givePermissionTo(self::PERMISOS);
}
```

Convención de nombres: `{modulo}.ver`, `{modulo}.crear`, `{modulo}.editar`, `{modulo}.eliminar` o `{modulo}.gestionar`.

### 4. Controller

```bash
php artisan make:controller PromocionController
```

Patrón usado en el proyecto (ver `ProductoController`):

```php
public function index(): View
{
    $promociones = Promocion::orderBy('nombre')->paginate(20);
    return view('promociones.index', compact('promociones'));
}

public function store(Request $request): RedirectResponse
{
    abort_unless(auth()->user()->can('promociones.gestionar'), 403);
    Promocion::create($request->validate([
        'nombre' => ['required', 'string', 'max:100'],
        'descuento_pct' => ['required', 'numeric', 'min:0', 'max:100'],
    ]));
    return redirect()->route('promociones.index')->with('ok', 'Promoción creada.');
}
```

### 5. Rutas

En `routes/web.php`, dentro del grupo `middleware('auth')`:

```php
Route::middleware('permission:promociones.ver')->group(function () {
    Route::get('/promociones', [PromocionController::class, 'index'])->name('promociones.index');
    Route::post('/promociones', [PromocionController::class, 'store'])
        ->name('promociones.store')
        ->middleware('permission:promociones.gestionar');
});
```

### 6. Vistas Blade

```
resources/views/promociones/
├── index.blade.php    @extends('layouts.app')
└── form.blade.php     (opcional, o modal en index)
```

Copiar estilo de `resources/views/categorias/index.blade.php` o `productos/index.blade.php`.

### 7. Menú lateral

En `resources/views/layouts/app.blade.php`:

```blade
@can('promociones.ver')
    <a href="{{ route('promociones.index') }}" class="...">Promociones</a>
@endcan
```

### 8. Servicio (solo si la lógica es compleja)

Si hay reglas de negocio que se usan en varios lugares (ventas, stock, AFIP):

```bash
php artisan make:class Services/PromocionService
```

Ejemplos existentes:

| Servicio | Para qué |
|----------|----------|
| `VentaService` | Crear/anular ventas, mover stock |
| `StockService` | Movimientos y ajustes de stock |
| `DesktopLicenseService` | Licencias firmadas desktop/móvil |
| `MoonCobroService` | Consulta mora Moon |

### 9. Probar

```bash
docker compose exec app php artisan migrate
docker compose exec app php artisan permission:cache-reset   # si permisos no aparecen
```

Abrí `/promociones` logueado como `admin`.

---

## Cómo se creó cada tipo de cosa (referencias reales)

### Autenticación y usuarios

| Pieza | Archivo |
|-------|---------|
| Login | `app/Http/Controllers/Auth/LoginController.php` |
| Vista login | `resources/views/auth/login.blade.php` |
| Modelo User + roles | `app/Models/User.php` (Spatie `HasRoles`) |
| Rutas | `routes/web.php` → `/login`, `/logout` |

### Multi-empresa

| Pieza | Archivo |
|-------|---------|
| Trait scope | `app/Models/Concerns/PerteneceAEmpresa.php` |
| Admin multi-empresa | `app/Http/Controllers/EmpresasAdminController.php` |
| Logo/color | migración `150002`, vista `empresa/edit.blade.php` |

### Productos y stock

| Pieza | Archivo |
|-------|---------|
| Migración tablas | `database/migrations/2026_06_12_100000_create_catalogo_tables.php` |
| Modelo | `app/Models/Producto.php`, `Stock.php` |
| Controller | `app/Http/Controllers/ProductoController.php` |
| Stock | `app/Services/StockService.php` |
| Vistas | `resources/views/productos/*` |
| Import CSV | métodos `importar` en ProductoController |

### POS web

| Pieza | Archivo |
|-------|---------|
| Controller | `app/Http/Controllers/PosController.php` |
| Vista + Alpine | `resources/views/pos/index.blade.php` (función `posApp()`) |
| Rutas | `GET /pos`, `GET /pos/catalogo`, `POST /pos/ventas` |
| Offline | `public/sw.js` + localStorage en la vista |
| Doc frontend | [13-frontend.md](./13-frontend.md) |

### Ventas y cajas

| Pieza | Archivo |
|-------|---------|
| Migración | `2026_06_12_120000_create_ventas_tables.php` |
| Servicio | `app/Services/VentaService.php` |
| Controllers | `VentaController`, `CajaController` |
| Ticket PDF/HTML | `resources/views/ventas/ticket.blade.php` |

### Facturación AFIP

| Pieza | Archivo |
|-------|---------|
| Migración | `2026_06_12_130000_create_afip_tables.php` |
| Controller | `app/Http/Controllers/FacturacionController.php` |
| Emisores/certificados | `app/Http/Controllers/EmisorController.php` |
| WSDL | `resources/afip/wsdl/` |

### API desktop / móvil (offline)

| Pieza | Archivo |
|-------|---------|
| Rutas | `routes/api.php` → prefijo `/api/desktop` |
| Controller | `app/Http/Controllers/Api/DesktopApiController.php` |
| Middleware token | `app/Http/Middleware/AuthenticateDesktop.php` |
| Licencias | `app/Services/Desktop/DesktopLicenseService.php` |
| Migración | `2026_06_13_100000_create_desktop_installations_table.php` |
| Config | `config/moon.php`, `config/desktop.php` |

Para agregar endpoint desktop: método en `DesktopApiController` + ruta en `api.php` + (opcional) sync en `desktop/electron/sync.js` y `mobile/www/js/bridge.js`.

### Seeders

| Seeder | Uso |
|--------|-----|
| `RolesYPermisosSeeder` | Roles base |
| `DatosInicialesSeeder` | Empresa, sucursal, admin |
| `ProductosEjemploSeeder` | 20 productos demo |
| Permisos por fase | migraciones `*_seed_permisos_*.php` |

Crear nuevo seeder:

```bash
php artisan make:seeder MiSeeder
# registrar en DatabaseSeeder o ejecutar con --class=
```

### Apps cliente (fuera de Laravel)

| App | Carpeta | No usa Artisan |
|-----|---------|----------------|
| Electron | `desktop/` | Consume API `/api/desktop` |
| Android | `mobile/` | Misma API |

---

## Comandos Artisan útiles al desarrollar

```bash
# Crear piezas
php artisan make:model Nombre -m          # modelo + migración
php artisan make:model Nombre -mcr        # + controller + resource
php artisan make:controller NombreController
php artisan make:migration create_xxx_table
php artisan make:seeder NombreSeeder
php artisan make:middleware NombreMiddleware

# BD
php artisan migrate
php artisan migrate:rollback
php artisan db:seed --class=NombreSeeder --force

# Permisos
php artisan permission:cache-reset

# Rutas y debug
php artisan route:list
php artisan route:list --name=productos
php artisan tinker
```

En Docker: prefijo `docker compose exec app` antes de cada comando.

---

## Reglas del proyecto (convenciones)

1. **Permisos antes que pantalla** — sin permiso en migración/seeder, no expongas la ruta.
2. **Multi-empresa** — modelos de negocio usan `PerteneceAEmpresa`.
3. **Lógica pesada en Services** — controllers delgados.
4. **Validación** — `$request->validate()` o Form Request; mensajes en español.
5. **Flash messages** — `->with('ok', '...')` o `->with('error', '...')`.
6. **Vistas** — extender `layouts.app`; POS es excepción (pantalla completa).
7. **Frontend** — Tailwind; JS solo con Alpine si hace falta ([13-frontend.md](./13-frontend.md)).
8. **API JSON** — controllers en `app/Http/Controllers/Api/`, rutas en `api.php`.
9. **Migraciones idempotentes de permisos** — `firstOrCreate`, no duplicar.
10. **Deploy** — push a GitHub → redeploy Dokploy → `php artisan migrate --force`.

---

## Flujo completo de un cambio nuevo

```
1. Rama git (opcional)
2. Migración + migrate
3. Modelo (+ PerteneceAEmpresa)
4. Migración permisos + migrate
5. Service (si aplica)
6. Controller
7. Rutas web.php o api.php
8. Vistas Blade
9. Link en sidebar
10. Probar local (docker compose)
11. git commit + push
12. Redeploy Dokploy
13. php artisan migrate --force en VPS
```

---

## Ejemplo mínimo copiable

Ver módulo **Categorías** (el más simple del proyecto):

- Controller: `app/Http/Controllers/CategoriaController.php`
- Vista: `resources/views/categorias/index.blade.php`
- Rutas: `routes/web.php` → `Route::resource` parcial
- Permisos: `categorias.ver`, `categorias.gestionar` en migración `100001`

Es el mejor punto de partida para clonar un CRUD nuevo.
