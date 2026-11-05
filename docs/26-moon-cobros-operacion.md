# Moon Cobros — estado y cómo retomarlo

Foto al **2026-11-05**. El diseño está en
[24-moon-cobros-specs.md](./24-moon-cobros-specs.md) y las reglas de trabajo en
[25-moon-cobros-prompt.md](./25-moon-cobros-prompt.md). Este documento dice
**qué está en producción, qué falta y cómo seguir** sin rehacer el análisis.

No hay contraseñas acá. Viven en `~/.config/posmoon/secrets/` (chmod 600).

## En una frase

El hub cobra en `https://pagos.posmoon.com.ar` con la cuenta real de Mercado
Pago de Moon. Ya importó el legacy (saldos 1 a 1) y se mantiene sincronizado.
**cmoon** es el único POS conectado, en modo aviso. El cobro del día a día
sigue haciéndolo el sistema viejo de cPanel: el hub todavía no emite cuotas ni
manda avisos.

## Qué está hecho

- [x] Módulo `app/Billing` en la rama `moon-cobros` (38 tests en verde).
- [x] Hub desplegado en Dokploy, HTTPS válido, 5 contenedores con límite de memoria.
- [x] DNS `pagos.posmoon.com.ar` → VPS.
- [x] Token de MP de la cuenta que hoy cobra los abonos (usuario `1188183100`).
- [x] Import del legacy: 45 clientes, 1.863 movimientos, saldos iguales 1 a 1.
      Sync horaria en solo lectura.
- [x] Checkout real de MP abre y cobra (probado en sandbox: pago `186827655085`,
      acreditado una sola vez, saldo en cero, el duplicado se ignora).
- [x] cmoon consulta el hub y muestra el aviso. No bloquea.
- [x] MCP de Dokploy apunta a `https://dokploy.cluna.ar/` (el puerto 3000 está cerrado).

## Qué falta, en orden

1. **Clave real del webhook de Mercado Pago.** Hoy hay una clave provisoria, así
   que MP notifica y el hub responde 401. Los pagos igual se acreditan: el
   barrido `billing:sincronizar-pendientes` corre cada 15 minutos. Ver más abajo.
2. **Un pago real chico** de los $150 del cliente piloto, con una tarjeta o
   cuenta que no sea la de Moon (MP no deja pagarse a uno mismo).
3. **Mail y WhatsApp.** No hay servidor de mail ni credenciales de YCloud, así
   que los avisos están apagados a propósito.
4. **Contraseñas por defecto `CMoon2026!`** todavía activas (ver tabla). No se
   cambiaron para no dejar a nadie afuera sin aviso.
5. **Conectar el resto de los POS** (Jamrod, Abisko, Cabañas, Esquina53). Exige
   merge a `main`, que tiene autodeploy. No hacerlo hasta validar el piloto.

## Dónde está cada cosa

| Qué | Valor |
|-----|-------|
| Repo / rama | `Moon-Gitub/cmoon`, rama `moon-cobros`. **No está en `main`.** |
| Dokploy | Proyecto `MoonCobros` (`aaX_SrxQd28kOeng5vco-`), environment `tPhhXkhxKfZ5ctKu5nDmk`, compose `COBROS` (`Vi0nAji_Ctg30yORqI7iv`), appName `mooncobros-99b9v4` |
| Compose | `./docker-compose.billing.yml`. `autoDeploy` **apagado**: cada cambio se despliega a mano. |
| URL | `https://pagos.posmoon.com.ar` (Let's Encrypt, vence 2027-02-03) |
| Panel | `https://pagos.posmoon.com.ar/login` → `/cobros`. Usuario `moon`. Clave en `~/.config/posmoon/secrets/moon-cobros-admin.txt` |
| Variables del hub | Dokploy + copia en `~/.config/posmoon/secrets/moon-cobros.env` |
| Base | `moon_cobros` en el MySQL del compose. Volumen `billing-mysql`. |
| Tenant piloto | Proyecto `POSMOON`, compose `CMOON` (`jo9ACdZxFOtS5N7de2IGN` / `cmoon-cmoon-ra45sh`). Rama cambiada de `security-hardening` a `moon-cobros` (`autoDeploy` sigue apagado). |
| Cliente piloto | Hub id **46**, "POSMoon interno (piloto)", dominio `cmoon.cluna.ar`, cargo de $150, `modo_cobro=solo_aviso`. Su API key está en `~/.config/posmoon/secrets/cmoon-billing-key.env`. |
| Backups | `~/Backups/moon-cobros/` (hub post-import y cmoon pre-deploy, ambos con `-- Dump completed`). |
| Bitácora local | `~/.config/posmoon/cuentas-notas.md`, sección "Moon Cobros". |

Contenedores: `mooncobros-99b9v4-{app,queue,scheduler,mysql,redis}-1`.
Techos: app 384M, queue 256M, scheduler 160M, mysql 512M, redis 96M.

## Cómo está configurado el hub

Encendido:

- `BILLING_HUB_ENABLED=true`
- `BILLING_LEGACY_SYNC=true` (relee el legacy cada hora, solo SELECT)
- `MP_COLLECTOR_ID=1188183100`, app de MP `3927436741225472` (la de
  `extras/cobro/cabezote.php` en demonew; es la que cobró los pagos reales de
  noviembre 2026)

Apagado a propósito, hasta el corte:

- `BILLING_GENERAR_CUOTAS=false` — las cuotas las sigue emitiendo el cron de cPanel.
  Si se prende ahora, se cobra dos veces.
- `BILLING_DUNNING_ENABLED=false` — sin mail ni WhatsApp no hay a quién avisar.
  El comando de las 10:00 corre y sale al instante.

Legacy: `MOON_DB_*` apunta a `cobrosposmooncom_db` en el hosting
(`107.161.23.45`, usuario `cobrosposmooncom_dbuser`). El usuario tiene más
permisos que SELECT; el importador solo lee. Excluye los ids `1,999`.

## Mercado Pago

| Cuenta | Usuario | Para qué |
|--------|---------|----------|
| Real | `1188183100` (MoonDesarrollos) | Producción. App `3927436741225472`. |
| Prueba | `2916747261` (`TESTUSER…`) | Sandbox. App `6921807486493458`. Token en `controladores/acreditar-pago.controlador.php` de demonew. |
| Otra, no usar | app `6132105836685151` | Es la única que ve el MCP de MP conectado por OAuth. **No es la cuenta que cobra.** |

### Cargar la clave del webhook

1. [Mercado Pago Developers](https://www.mercadopago.com.ar/developers/panel/app/3927436741225472/webhooks):
   URL de producción `https://pagos.posmoon.com.ar/webhooks/mercadopago`,
   evento **Pagos**, y copiar la clave secreta.
2. Reemplazar `MP_WEBHOOK_SECRET` en el Environment del compose `COBROS` y en
   `~/.config/posmoon/secrets/moon-cobros.env`.
3. Redesplegar el hub (ver "Desplegar" más abajo) y correr
   `php artisan billing:diagnostico`.
4. Un pago de prueba tiene que dejar `billing_webhook_events.firma_valida = 1`.

El header `x-signature` se guarda desde el commit `5de9134`; sirve para
diagnosticar un 401 sin volver a pedir el header a MP.

## cmoon (piloto)

Variables en el compose `CMOON`, que `docker-compose.yml` recién pasa a la app
desde el commit `4f05918`:

```
MOON_BILLING_URL=https://pagos.posmoon.com.ar
MOON_BILLING_KEY=<mbk_… del cliente 46>
```

`MOON_BILLING_ENFORCE` no está. Sin esa variable el POS **avisa y no bloquea**,
aunque el cliente estuviera en modo `bloqueo`. Son las dos llaves de la spec.

Verificar desde el contenedor:

```bash
docker exec cmoon-cmoon-ra45sh-app-1 php artisan tinker --execute="echo json_encode(app(App\Services\Moon\MoonBillingClient::class)->estado());"
```

Tiene que decir `"fuente":"hub"`. Conteos al momento del deploy (tienen que
seguir igual salvo operación normal): migraciones 48, ventas 54, productos 26,
clientes 3, proveedores 1, usuarios 1.

## Conectar otro POS

Recién cuando el piloto esté aceptado y `moon-cobros` esté mergeado a `main`
(los otros cuatro tienen `autoDeploy` sobre `main`: **apagarlo antes del merge**
y desplegar de a uno; un push a `main` dispara cinco builds y tumba el VPS).

Por cada tenant:

1. Backup de su MySQL, bajado fuera del VPS, con `-- Dump completed`.
2. En el hub, crear el cliente (o usar el importado del legacy) y
   `php artisan billing:api-key {id}`.
3. Agregar `MOON_BILLING_URL` y `MOON_BILLING_KEY` al Environment. Sin
   `MOON_BILLING_ENFORCE`.
4. Deploy de ese compose solo, y controlar que los conteos no se movieron.

El bloqueo se prende por cliente (`modo_cobro=bloqueo` en el panel) y por
tenant (`MOON_BILLING_ENFORCE=true`). Nunca los dos el mismo día sin haber
visto el aviso funcionar.

## Contraseñas por defecto todavía activas

Usuarios con la clave de seeder `CMoon2026!` al 2026-11-05:

| POS | Usuarios |
|-----|----------|
| cmoon | `admin` |
| Jamrod | `bicicleteria`, `tienda`, `moondesa`, `impacto` |
| Cabañas del Cerro | `alfredo`, `vendedor` |

Esquina53 y Abisko no la tienen. En el hub, el `admin` sembrado
(`admin@cmoon.local`) quedó **desactivado** y con clave aleatoria.

## Comandos del día a día

Dentro de `mooncobros-99b9v4-app-1`:

```bash
php artisan billing:diagnostico          # token, collector, URL, legacy, mail
php artisan billing:importar-legacy      # incremental; imprime si un saldo no cierra
php artisan billing:sincronizar-pendientes
php artisan billing:conciliar --dias=3
php artisan schedule:list                # import :00, barrido */15, conciliar 07:00, avisos 10:00
```

Scheduler real: el contenedor `scheduler` duerme hasta el próximo cuarto de
hora y corre `schedule:run`. No usa el loop de 60 s de los tenants.

## Desplegar

`autoDeploy` está apagado. **Ojo:** en Dokploy, "Redeploy" reconstruye con el
código ya clonado y **no** hace `git pull`. Para traer un commit nuevo hay que
usar **Deploy** (`compose.deploy`), no Redeploy.

```bash
# API: https://dokploy.cluna.ar/api  (header x-api-key)
curl -X POST "$API/compose.deploy" -H "x-api-key: $KEY" -H "Content-Type: application/json" \
  -d '{"composeId":"Vi0nAji_Ctg30yORqI7iv"}'
```

Después: `docker ps --filter name=mooncobros`, `curl https://pagos.posmoon.com.ar/up`
y `billing:diagnostico`.

## DNS

- `pagos.posmoon.com.ar` A `72.60.0.249`, ttl 300. Agregado el 2026-11-05 en la
  zona del cPanel `posmoonar` (`uapi DNS mass_edit_zone`). El apex
  `posmoon.com.ar` sigue en `107.161.23.45`. No se tocó ningún otro registro.
- `cluna.ar` está en Cloudflare y no tiene comodín. `cobros.cluna.ar` ya está
  cargado como dominio del compose (`MHGN3I87NrA5DC_XONywW`) pero **sin registro
  DNS**, así que no tiene certificado. Cuando exista el A record, Traefik lo
  emite solo.
- Los dominios `*.traefik.me` no sirven: Let's Encrypt responde 429 a esa zona.

## Trampas que ya pisamos

- **Los tests no pueden usar sqlite.** Las migraciones del POS tienen SQL de
  MySQL. `phpunit.xml` fuerza la base `cmoon_testing` y `tests/TestCase.php`
  aborta si el nombre no termina en `_testing`: un `RefreshDatabase` contra la
  base real la vacía. Ya pasó una vez en el MySQL **local** de desarrollo; no
  afectó producción.
- **Las migraciones de billing viven en `database/migrations/billing`** y solo
  se cargan con `BILLING_HUB_ENABLED=true`. Un tenant no las corre. Por eso
  cmoon sigue en 48 migraciones.
- **El compose de los tenants lista las variables una por una.** Poner algo en
  el Environment de Dokploy no alcanza: hay que declararlo en
  `docker-compose.yml`. Antes de `4f05918` ninguna `MOON_*` llegaba a la app.
- **`billing:admin`** choca si ya existe un usuario con el mismo nombre. Desde
  `5de9134` usa el email completo cuando el nombre corto está ocupado. El hub
  no debe correr `db:seed`: siembra el admin del POS.
- **Sandbox:** la preferencia exige `back_url` https, así que un hub local tiene
  que anunciar `BILLING_PUBLIC_URL` con https aunque sirva en http. El vendedor
  de prueba es `2916747261` y hace falta `MP_ACEPTAR_SANDBOX=true`.
- **Pagarse a uno mismo** con la cuenta 1188183100 falla del lado de MP.
- **Memoria del VPS:** al desplegar quedaban ~3,2 GB disponibles de 7,8. No
  sumar servicios sin mirar `free -h` (columna `available`).

## Corte del legacy (cuando se decida)

Está descripto como fase F5 en la spec. El orden corto:

1. Backup de `cobrosposmooncom_db` y del hub.
2. Una última corrida de `billing:importar-legacy` y revisar que no haya
   `saldos_distintos`.
3. Apagar el cron de cuotas en cPanel (`clientes_generar_cuotas.php`).
4. `BILLING_GENERAR_CUOTAS=true` en el hub y redesplegar.
5. Recién ahí `BILLING_DUNNING_ENABLED=true`, con mail o WhatsApp ya probados.
6. Dejar `BILLING_LEGACY_SYNC` prendido unos días para cazar un movimiento que
   entre tarde por el sistema viejo, y apagarlo al final.
