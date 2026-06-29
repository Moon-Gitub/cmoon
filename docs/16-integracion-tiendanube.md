# Integración con Tiendanube

POSMoon permite conectar tu tienda de Tiendanube para sincronizar productos, stock y recibir órdenes automáticamente.

## Índice

1. [Requisitos previos](#requisitos-previos)
2. [Crear app en Tiendanube Partners](#crear-app-en-tiendanube-partners)
3. [Configurar POSMoon](#configurar-posmoon)
4. [Conectar tu tienda](#conectar-tu-tienda)
5. [Configuración de sincronización](#configuración-de-sincronización)
6. [Sincronizar productos](#sincronizar-productos)
7. [Sincronizar stock](#sincronizar-stock)
8. [Importar órdenes](#importar-órdenes)
9. [Historial y logs](#historial-y-logs)
10. [Preguntas frecuentes](#preguntas-frecuentes)

---

## Requisitos previos

- Cuenta en [Tiendanube](https://www.tiendanube.com/) con tienda activa
- Acceso de administrador en POSMoon
- Permiso "Editar empresa" en tu usuario

---

## Crear app en Tiendanube Partners

Para conectar POSMoon con tu tienda, primero necesitás crear una aplicación en el portal de partners de Tiendanube.

### Paso 1: Acceder al portal de partners

1. Ir a [partners.tiendanube.com](https://partners.tiendanube.com/)
2. Iniciar sesión o crear cuenta de partner (es gratis)

### Paso 2: Crear una nueva aplicación

1. En el panel de partners, ir a **"Apps"** → **"Crear app"**
2. Completar los datos:
   - **Nombre**: `POSMoon Sync` (o el nombre que prefieras)
   - **Descripción**: Sincronización con sistema de gestión POSMoon
   - **URL de redirección**: `https://TU-DOMINIO/integraciones/tiendanube/callback`
   
   > Ejemplo: `https://cmoon.aiporvos.com/integraciones/tiendanube/callback`

3. Seleccionar los **permisos (scopes)**:
   - ✅ Read products / Write products
   - ✅ Read orders / Write orders
   - ✅ Read customers / Write customers
   - ✅ Read coupons

4. Guardar la aplicación

### Paso 3: Obtener credenciales

Después de crear la app, vas a ver:
- **Client ID**: número de identificación de tu app
- **Client Secret**: clave secreta (no compartir)

Guardá estos datos, los vas a necesitar en el siguiente paso.

---

## Configurar POSMoon

### Variables de entorno

Agregá las siguientes variables en el archivo `.env` de POSMoon:

```env
TIENDANUBE_CLIENT_ID=123456
TIENDANUBE_CLIENT_SECRET=abcdef123456789
TIENDANUBE_USER_AGENT="POSMoon (tu@email.com)"
```

Reemplazá:
- `123456` con tu Client ID real
- `abcdef123456789` con tu Client Secret real
- `tu@email.com` con un email de contacto

### Aplicar cambios

Si usás Dokploy u otro sistema de deploy:
1. Agregá las variables en la configuración de entorno
2. Hacé redeploy de la aplicación
3. Ejecutá las migraciones: `php artisan migrate`

---

## Conectar tu tienda

### Paso 1: Acceder al módulo

1. Iniciar sesión en POSMoon
2. En el menú lateral, hacer click en **"Tiendanube"**

### Paso 2: Conectar

1. Click en el botón **"Conectar con Tiendanube"**
2. Se abre Tiendanube pidiendo autorización
3. Revisar los permisos y hacer click en **"Autorizar"**
4. Serás redirigido de vuelta a POSMoon

### Paso 3: Verificar conexión

Si la conexión fue exitosa, vas a ver:
- ✅ Estado "Tienda conectada"
- Nombre y URL de tu tienda
- Panel de configuración habilitado

---

## Configuración de sincronización

Una vez conectada la tienda, podés configurar qué se sincroniza.

### Opciones disponibles

| Opción | Descripción |
|--------|-------------|
| **Productos** | Exportar catálogo de POSMoon a Tiendanube |
| **Stock** | Actualizar stock automáticamente cuando cambia |
| **Órdenes** | Importar ventas de Tiendanube como ventas en POSMoon |
| **Clientes** | Sincronizar datos de clientes |
| **Crear productos automáticamente** | Crear productos en POSMoon cuando se detectan en Tiendanube |

### Sucursal de stock

Seleccioná la sucursal de la cual se tomará el stock para sincronizar con Tiendanube. El stock de esa sucursal es el que verán tus clientes online.

### Guardar configuración

Después de seleccionar las opciones, click en **"Guardar configuración"**.

---

## Sincronizar productos

### Exportar catálogo a Tiendanube

1. En el panel de Tiendanube, click en **"Sincronizar productos"**
2. Se inicia un proceso en segundo plano
3. Revisá los logs para ver el progreso

### Qué se sincroniza

| Campo POSMoon | Campo Tiendanube |
|---------------|------------------|
| Código | SKU |
| Nombre | Nombre del producto |
| Descripción | Descripción |
| Precio de venta | Precio |
| Precio de compra | Costo (interno) |
| Categoría | Categoría |
| Activo | Publicado |

### Productos vinculados

En la sección **"Ver productos vinculados"** podés ver:
- Qué productos de POSMoon están conectados con Tiendanube
- ID del producto en Tiendanube
- Última fecha de sincronización

---

## Sincronizar stock

### Sincronización automática

Si activaste "Sincronizar stock", cada vez que cambie el stock en POSMoon (por venta, compra o ajuste), se actualiza automáticamente en Tiendanube.

### Sincronización manual

Para forzar una sincronización completa de stock:
1. Click en **"Sincronizar stock"**
2. Se actualizan todos los productos vinculados

### Consideraciones

- Solo se sincroniza el stock de la **sucursal configurada**
- Si tenés múltiples sucursales, elegí la que representa tu inventario online
- El stock en Tiendanube se **reemplaza** con el de POSMoon (no se suma)

---

## Importar órdenes

### Importación automática (webhooks)

Cuando un cliente paga en tu tienda Tiendanube:
1. Tiendanube envía una notificación a POSMoon
2. POSMoon crea automáticamente una venta
3. Se descuenta el stock correspondiente
4. Se vincula o crea el cliente

Las ventas importadas aparecen con:
- **Origen**: `tiendanube`
- **Número**: `TN-000001`, `TN-000002`, etc.

### Importación manual

Para importar órdenes que no llegaron por webhook:
1. Click en **"Importar órdenes recientes"**
2. Se importan las órdenes pagadas de los últimos 7 días
3. Las órdenes ya importadas se ignoran (no se duplican)

### Ver ventas de Tiendanube

En **Ventas**, podés filtrar por origen para ver solo las ventas online.

---

## Historial y logs

### Ver actividad

Click en **"Ver historial de sincronización"** para ver:
- Todas las operaciones realizadas
- Errores de sincronización
- Webhooks recibidos

### Filtrar logs

Podés filtrar por tipo:
- **Productos**: sincronización de catálogo
- **Stock**: actualizaciones de inventario
- **Órdenes**: importación de ventas
- **Webhooks**: notificaciones de Tiendanube
- **Errores**: operaciones fallidas

### Información de cada log

- Fecha y hora
- Tipo de operación
- Dirección (Push → TN / Pull ← TN / Webhook)
- Estado (OK / Error)
- Detalle del request/response

---

## Preguntas frecuentes

### ¿Qué pasa si desconecto la tienda?

- Se elimina la configuración de POSMoon
- Los webhooks se desregistran de Tiendanube
- Los productos y ventas ya sincronizados **no se borran**
- Podés volver a conectar en cualquier momento

### ¿Puedo conectar varias tiendas Tiendanube?

Actualmente POSMoon soporta **una tienda por empresa**. Si necesitás múltiples tiendas, contactá a soporte.

### ¿El stock se sincroniza en ambas direcciones?

No. POSMoon es el **maestro de stock**:
- POSMoon → Tiendanube: ✅ Sí
- Tiendanube → POSMoon: ❌ No (solo se descuenta al importar órdenes)

### ¿Qué pasa si un producto no tiene SKU?

Se genera un código automático: `TN-{id_producto}`.

### ¿Cómo manejo las variantes de productos?

Actualmente se sincroniza la **primera variante** de cada producto. Soporte para múltiples variantes está en desarrollo.

### ¿Por qué no llegan las órdenes automáticamente?

Verificá:
1. Que la opción "Importar órdenes" esté activada
2. Que el webhook esté registrado (mirá en Tiendanube → Apps → Tu app → Webhooks)
3. Que la URL de webhook sea accesible públicamente
4. Los logs de errores en POSMoon

### ¿Puedo importar productos de Tiendanube a POSMoon?

Sí. Activá **"Crear productos automáticamente"** y luego:
- Los productos nuevos en Tiendanube se crean en POSMoon automáticamente
- También podés usar el botón **"Importar de Tiendanube"** para importar todo el catálogo

### ¿Cómo actualizo la información de un producto ya vinculado?

Simplemente editá el producto en POSMoon y guardá. Si "Sincronizar productos" está activo, se actualizará en Tiendanube automáticamente.

### ¿Qué medios de pago se asignan a las ventas importadas?

Se busca un medio de pago llamado "Tiendanube" o similar. Si no existe, se usa el primer medio de pago activo.

**Recomendación**: Creá un medio de pago llamado "Tiendanube" para identificar fácilmente estas ventas.

---

## Soporte

Si tenés problemas con la integración:

1. Revisá los logs en **Tiendanube → Ver historial**
2. Verificá que las credenciales en `.env` sean correctas
3. Probá la conexión con el botón **"Probar conexión"**
4. Contactá a soporte con capturas de los errores

---

## Resumen de URLs importantes

| Recurso | URL |
|---------|-----|
| Panel de integración | `/integraciones/tiendanube` |
| Callback OAuth | `/integraciones/tiendanube/callback` |
| Webhook Tiendanube | `/webhooks/tiendanube` |
| Partners Tiendanube | [partners.tiendanube.com](https://partners.tiendanube.com/) |
