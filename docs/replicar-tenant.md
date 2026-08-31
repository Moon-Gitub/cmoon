# Replicar un tenant POSMoon — guía operativa

> Escrito el 31/08/2026 después de una noche en que el VPS se colgó una hora con
> los cinco clientes adentro. Todo lo que está acá salió de diagnosticar esa caída.
> Si vas a replicar, modificar o desplegar un tenant POSMoon, leé esto antes.

---

## 0. Cómo está armado esto (entender antes de tocar)

**Los cinco tenants deployan del MISMO repo y el MISMO `docker-compose.yml`.**
No hay un compose por cliente. Se diferencian solo por las variables de entorno
que carga Dokploy en cada servicio.

Consecuencia directa: **un cambio al compose afecta a los cinco** en cuanto cada
uno redeploye. No existe "tocar solo el de un cliente" desde el repo.

Cada stack tiene 7 servicios: `app`, `queue`, `scheduler`, `mysql`, `redis`,
`adminer` y `data-migrator` (one-shot, corre y termina).

Dokploy le pone a cada proyecto un nombre aleatorio, así que el nombre del
contenedor **no dice de qué cliente es**. Mapeo al 31/08/2026:

| Stack en `docker ps` | Cliente | Dominio |
|---|---|---|
| `cmoon-cmoon-ra45sh` | POSMOON (base) | cmoon.aiporvos.com |
| `compose-input-cross-platform-alarm-mbgk1z` | Esquina53 | esquina53.cluna.ar |
| `compose-index-virtual-sensor-rqnvyf` | CabañasDelCerro | cabanasdelcerro.cluna.ar |
| `compose-bypass-auxiliary-panel-t19ign` | Abisko | abisko.cluna.ar |
| `compose-calculate-cross-platform-sensor-npgw9x` | Jamrod | jamrod.cluna.ar |

Para mapear un stack a su cliente sin adivinar:

```bash
docker inspect <stack>-app-1 --format '{{range .Config.Env}}{{println .}}{{end}}' | grep APP_URL
```

**Infra:** VPS Hostinger KVM2 — 2 vCPU, 8 GB RAM, 96 GB disco — en `72.60.0.249`.
Dokploy en `https://dokploy.cluna.ar` (el puerto 3000 está cerrado a propósito).

---

## 1. Reglas duras — no las rompas nunca

Comandos prohibidos sobre este servidor:

```bash
docker compose down -v          # el -v borra volúmenes
docker volume rm ...
docker volume prune
docker system prune --volumes
docker system prune -a --volumes
```

Además:

- **Nunca borres ni recrees un servicio desde la UI de Dokploy.** Borrar un
  Compose puede llevarse los volúmenes. Se edita el compose y se redeploya.
  Jamás delete + create.
- **Nunca renombres la clave de un volumen** (ver sección 3).
- Para frenar contenedores: `stop`, nunca `down`.
- El servidor tiene otros ~50 contenedores que no son POSMoon (n8n, Evolution
  API, Traefik, Dokploy). **No los toques.**
- Antes de cualquier cambio que toque datos: backup primero, verificado.

---

## 2. Checklist para replicar un tenant nuevo

1. **Crear el proyecto en Dokploy** con un nombre reconocible. Se puede hacer
   desde la UI o por la API de Dokploy — el agente ya lo automatiza hoy.
   Dokploy le asigna igual un nombre interno aleatorio; no se puede evitar, por
   eso el mapeo de la sección 0 hay que mantenerlo actualizado.
2. **Source:** GitHub → repo `Moon-Gitub/cmoon`, branch `main`,
   Compose Path `./docker-compose.yml`.
3. **Cargar las variables de entorno** (sección 10). Sin ellas el deploy falla
   en el parseo del compose, porque varias usan `:?`.
4. **Dominio:** agregar SOLO el del servicio `app`. Nunca el de `adminer`
   (sección 7).
5. **Deploy.** Verificar con la sección 12.
6. **Actualizar el mapeo** de la sección 0 con el nombre aleatorio nuevo. Si no,
   en un mes nadie va a saber qué stack corresponde a qué cliente.
7. **Dejar `autoDeploy` apagado** hasta terminar de verificar el tenant nuevo.

---

## 3. Volúmenes persistentes — la trampa más cara

El compose ya declara tres volúmenes nombrados, y **están bien**:

```yaml
volumes:
  app-storage:      # /var/www/html/storage/app  (uploads, adjuntos)
  mysql-data:       # /var/lib/mysql
  redis-data:       # /data
```

**Docker Compose prefija los volúmenes con el nombre del proyecto.** El volumen
`mysql-data` del proyecto `cmoon-cmoon-ra45sh` existe en el host como
`cmoon-cmoon-ra45sh_mysql-data`. Es decir: **los datos de cada cliente ya están
aislados por proyecto, sin hacer nada.**

### La regla

**NO renombres la clave del volumen para "que se entienda de qué cliente es".**

Si cambiás `mysql-data:` por `esquina53-mysql-data:`, Docker no renombra nada:
crea un volumen nuevo y vacío, lo monta, y **la base aparece en blanco**. El dato
viejo queda huérfano en disco. Parece pérdida total y es un susto evitable.

### Qué verificar en cada tenant

```bash
docker inspect <stack>-mysql-1 --format '{{json .Mounts}}' | jq
```

Debe mostrar `"Type": "volume"` con un `"Name"` que empiece con el nombre del
proyecto. Casos problemáticos:

| Caso | Qué significa | Qué hacer |
|---|---|---|
| Volumen nombrado | correcto | nada |
| Volumen anónimo (hash largo) | sobrevive al redeploy pero es frágil | adoptar (ver abajo) |
| Bind mount a ruta del host | funciona, pero atado al servidor | reportar |
| Sin volumen | **los datos se pierden en cada deploy** | urgente |

### Adoptar un volumen anónimo, sin perder datos

```bash
docker compose stop                      # STOP, nunca down
docker volume create <nuevo>
docker run --rm -v <anonimo>:/from -v <nuevo>:/to alpine sh -c "cd /from && cp -a . /to"
# actualizar el compose, levantar, y VERIFICAR contando filas
# el volumen anónimo NO se borra hasta confirmar que todo está
```

---

## 4. Límites de memoria — obligatorios

El servidor tiene 8 GB para ~60 contenedores. **Sin techo, un solo contenedor
descontrolado cuelga la máquina entera y se lleva a los cinco clientes.**
Pasó: el OOM killer mató `mysqld` de tres clientes distintos (25/08, 28/08 y
31/08) y la madrugada del 31/08 el server quedó una hora sin responder.

Valores en el compose:

```yaml
deploy:
  resources:
    limits:
      memory: 384M     # app
      # queue 256M · scheduler 160M · mysql 512M · redis 96M · adminer 64M
```

Consumo real medido después de aplicarlos (para calibrar si hay que ajustar):

| Servicio | Techo | Uso real |
|---|---|---|
| mysql | 512M | 190–225 MiB |
| app | 384M | ~47 MiB |
| queue | 256M | ~46 MiB |
| scheduler | 160M | ~46 MiB |
| redis | 96M | ~4 MiB |
| adminer | 64M | ~11 MiB |

**Regla al ajustar:** medí primero con `docker stats --no-stream`, y poné el
techo con margen. Un límite por debajo del uso real hace que el kernel mate el
contenedor en producción — el remedio peor que la enfermedad.

Los límites son techos, no reservas. **No crean RAM.** Evitan que uno se lleve
todo por delante; no resuelven que la máquina esté sobrevendida.

---

## 5. MySQL — bajar el consumo

```yaml
command:
  - --character-set-server=utf8mb4
  - --collation-server=utf8mb4_unicode_ci
  - --innodb-buffer-pool-size=128M
  - --performance-schema=OFF
```

**`--performance-schema=OFF` es la línea que más rinde**: MySQL 8 reserva 200 MB
o más solo para eso. Medido en Jamrod: el MySQL pasó de 428 MB a 224 MB.

El costo es perder el `performance_schema` para diagnóstico interno de MySQL. Si
alguna vez lo necesitás, sacás la línea y redeployás.

Verificar que los flags entraron:

```bash
docker exec -e MYSQL_PWD="$pw" <stack>-mysql-1 mysql -u root -e \
  "SELECT @@innodb_buffer_pool_size/1024/1024 AS pool_mb, @@performance_schema;"
```

### Sobre las dos bases por tenant

Cada MySQL contiene **dos** bases:

- `cmoon` — la aplicación: 66 tablas, 42 migraciones
- `<cliente>_legacy` — el dump del sistema viejo, importado por `data-migrator`

Por eso el conteo de tablas difiere entre tenants (7 a 26 tablas legacy según el
cliente). **No es drift de esquema.** Para comparar tenants de verdad:

```sql
SELECT COUNT(*) FROM cmoon.migrations;   -- deben dar 42 en todos
```

Si alguno da menos, ese tenant tiene la base incompleta.

---

## 6. Redis

Ya viene con `--appendonly yes` y `--requirepass`. Está bien, no lo toques.

**Trampa si alguna vez lo configurás desde cero:** no agregues `--appendonly yes`
al `command` y reinicies de una. Con AOF activado y sin archivo AOF previo, Redis
arranca vacío e **ignora el `dump.rdb`**. Se pierde lo que hubiera en memoria,
incluidos los jobs encolados. El orden correcto es al revés:

```bash
docker exec <redis> redis-cli CONFIG SET appendonly yes   # genera el AOF desde memoria
# confirmar que el AOF existe en el volumen
# recién ahí, fijarlo en el compose
```

Redis acá guarda cache, sesiones **y la cola de jobs** (`QUEUE_CONNECTION=redis`).
Por eso **no le pongas `maxmemory` con política de eviction**: descartaría jobs y
sesiones en silencio.

---

## 7. Adminer — nunca expuesto

Al 31/08/2026 había **9 dominios de Adminer publicados en internet**, dos de ellos
sirviendo un login de base de datos sin ninguna autenticación por delante. Se
cerraron todos.

### La regla

**El servicio `adminer` no lleva dominio en Dokploy. Nunca. Bajo ninguna excusa.**

Sin dominio, el contenedor corre pero Traefik no lo enruta, y desde afuera da 404.

### Cómo entrar a una base cuando hace falta

```bash
# opción A — directo, sin nada expuesto
docker exec -it <stack>-mysql-1 mysql -u root -p

# opción B — Adminer por túnel SSH
ssh -L 8080:localhost:8080 root@72.60.0.249
# y abrir http://localhost:8080
```

### Detalle importante sobre cómo se expone

El compose del repo **no tiene `ports:`** en ningún servicio, y eso está bien.
La exposición no viene de ahí: **Dokploy inyecta labels de Traefik al desplegar**,
a partir de los dominios cargados en su UI. Las labels no están en el repo.

Consecuencia: **editar el compose no sirve para cerrar un Adminer expuesto.** El
próximo deploy vuelve a inyectar las labels. Hay que borrar el dominio en Dokploy.

Verificar que no quedó ninguno:

```sql
-- en dokploy-postgres
SELECT COUNT(*) FROM domain WHERE "serviceName" = 'adminer';   -- debe dar 0
```

### Si querés eliminarlo del todo

Borrar el bloque `adminer` del `docker-compose.yml`. Ahorra ~11 MB por tenant y
elimina la posibilidad de que alguien le cargue un dominio por error.

---

## 8. Dominios en Dokploy

- Solo el servicio `app` lleva dominio.
- El tag que muestra cada tarjeta en la UI (`app` / `adminer`) es lo que hay que
  mirar, **no el nombre del dominio**. Hubo un Adminer llamado
  `cmoon-cmoon-ra45sh-2d1e0e-72-60-0-249.traefik.me`, sin la palabra "db" en
  ningún lado.
- Ojo con los `.traefik.me`: es un DNS público comodín que resuelve la IP escrita
  en el propio hostname. `jamrod-adminer-72-60-0-249.traefik.me` apunta a este
  servidor y es una dirección válida y adivinable. No son inofensivos.
- El diálogo de borrado de Dokploy **no dice qué dominio va a borrar**. Verificá
  antes de confirmar.

---

## 9. autoDeploy — la trampa que tumba el servidor

Los cinco tenants tienen `autoDeploy` activo y apuntan a `main`.

**Un push a `main` dispara cinco builds de Docker simultáneos** en una máquina de
2 vCPU. Es la operación más pesada que existe en este servidor y muy probablemente
lo cuelgue, con los cinco clientes en pleno redeploy.

### Procedimiento correcto para cambiar el compose

1. Commitear a una **rama**, no a `main`
2. Apuntar **un solo tenant** a esa rama (Dokploy → General → Branch) y deployar
3. Verificar (sección 12)
4. **Apagar `autoDeploy`** en los tenants restantes
5. Mergear la rama a `main`
6. Deployar de a uno desde la UI, verificando cada uno
7. Devolver el tenant piloto a `main` y reactivar los `autoDeploy`

Orden sugerido para el paso 6: del tenant con la base más chica al más grande.

Consultar el estado:

```sql
SELECT name, "autoDeploy", branch FROM compose;
```

### El webhook depende del dominio del panel — y falla en silencio

El `autoDeploy` no lo dispara Dokploy solo: lo dispara un **webhook que GitHub
llama** en cada push. Esa URL contiene el dominio del panel de Dokploy.

**Si cambia la URL del panel, o se cierra el puerto por el que respondía, todos
los webhooks quedan apuntando a una dirección muerta.** Y el modo de falla es el
peor posible: **silencioso**. Pusheás, GitHub dice que entregó, y no pasa nada.
No hay error en ningún lado.

Pasó el 31/08/2026: al cerrar el puerto 3000 y mover el panel a
`https://dokploy.cluna.ar`, los webhooks siguieron apuntando a
`http://72.60.0.249:3000/...` y el auto-deploy dejó de funcionar en **todos** los
servicios con origen GitHub del servidor, no solo en los POSMoon.

**Cómo detectarlo:**

```bash
# ¿llegó algo a Dokploy después del push?
docker service logs dokploy --since 10m 2>&1 | grep -i webhook
# ¿se registró un deployment nuevo?
# (en dokploy-postgres) SELECT co.name, d.status, d."createdAt"
#   FROM deployment d JOIN compose co ON co."composeId"=d."composeId"
#   ORDER BY d."createdAt" DESC LIMIT 5;
```

Si no hay líneas de webhook y no hay deployments nuevos, el webhook está roto.

**Cómo arreglarlo:** la URL correcta está en Dokploy, en cada servicio, pestaña
**Deployments**. Se copia de ahí y se reemplaza en el repo:
GitHub → Settings → Webhooks.

**Hay que hacerlo repo por repo.** Cada servicio con origen GitHub tiene su
propio webhook con su propio token.

> Mientras el webhook esté roto, pushear a `main` es inofensivo: no dispara
> nada. El riesgo de los builds simultáneos vuelve recién cuando se arregle.

---

## 10. Variables de entorno en Dokploy

**Obligatorias** (el compose usa `:?`, así que si falta una el deploy ni arranca):

```
APP_KEY              base64:...   (generar: echo "base64:$(openssl rand -base64 32)")
APP_URL              https://<cliente>.cluna.ar
DB_PASSWORD
DB_ROOT_PASSWORD
REDIS_PASSWORD
```

**Recomendadas:**

```
APP_NAME             POSMoon
DB_DATABASE          cmoon
DB_USERNAME          cmoon
ADMIN_PASSWORD       (la del usuario admin@cmoon.local)
LEGACY_DB_DATABASE   <cliente>_legacy
```

### Reglas

- **Credenciales distintas por tenant.** Nada de reusar la misma
  `DB_ROOT_PASSWORD` entre clientes.
- Los secretos van **solo** en Dokploy. Nunca en el compose ni commiteados.
- **`LEGACY_DB_DATABASE` hay que setearla siempre.** El default del compose era
  `jamrod_legacy` — el nombre de la base de otro cliente — en los cinco tenants.
  Se cambió a `legacy`, neutro, pero el default nunca debería usarse.

### El usuario admin

El seed crea `admin@cmoon.local` con la contraseña de `ADMIN_PASSWORD`. Los
usuarios `*@legacy-import.local` vienen del import y no tienen contraseña usable.

---

## 11. Backups antes de tocar nada

```bash
BK=/root/backups/$(date +%F)
mkdir -p "$BK"

pw=$(docker inspect <stack>-mysql-1 --format '{{range .Config.Env}}{{println .}}{{end}}' \
     | grep '^MYSQL_ROOT_PASSWORD=' | cut -d= -f2-)

docker exec -e MYSQL_PWD="$pw" <stack>-mysql-1 mysqldump -u root \
  --single-transaction --routines --triggers --events --all-databases \
  > "$BK/<cliente>-mysql.sql"

# volumen de uploads
docker run --rm -v <stack>_app-storage:/data -v "$BK":/bk \
  alpine tar czf /bk/<cliente>-storage.tar.gz -C /data .
```

**Verificar, no alcanza con generar:**

- el `.sql` pesa más de 0 y termina con `-- Dump completed`
- `grep -c 'CREATE TABLE'` da un número coherente
- `tar tzf` lista sin errores

**Y bajarlo del servidor.** Un backup que vive solo en la máquina que vas a
modificar no es un backup.

---

## 12. Verificación después de cada deploy

```bash
# 1. los 7 contenedores, healthy
docker ps -a --filter "name=<stack>" --format '{{.Names}}\t{{.Status}}'

# 2. los límites se aplicaron (0 = sin límite, mal)
docker inspect <stack>-mysql-1 --format '{{.HostConfig.Memory}}'

# 3. MySQL tomó los flags
docker exec -e MYSQL_PWD="$pw" <stack>-mysql-1 mysql -u root -e \
  "SELECT @@innodb_buffer_pool_size/1024/1024, @@performance_schema;"

# 4. las migraciones están completas
docker exec -e MYSQL_PWD="$pw" <stack>-mysql-1 mysql -u root -N -e \
  "SELECT COUNT(*) FROM cmoon.migrations;"     # debe dar 42

# 5. conteo de filas contra el valor de antes del deploy
```

Desde afuera:

```bash
curl -s -o /dev/null -w "%{http_code}\n" https://<cliente>.cluna.ar/       # 302
curl -s -o /dev/null -w "%{http_code}\n" https://<cliente>-db.cluna.ar/    # 404 o sin resolver
```

Si un conteo de filas no coincide: revertir al compose anterior y no avanzar.

---

## 13. Diagnóstico

### Las zonas horarias no coinciden — cuidado al correlacionar

| Fuente | Zona |
|---|---|
| `journalctl`, `dmesg`, `uptime`, `docker ps` | **UTC** |
| Logs de las apps POSMoon | **ART (-0300)**, por `APP_TIMEZONE` |

Son 3 horas de diferencia. Si un error de la app y un evento del kernel no te
cierran en el tiempo, es esto.

### Comandos útiles

```bash
# ¿el OOM killer mató algo?
journalctl -k -b | grep -i -E "oom-kill|killed process"
# y para el boot ANTERIOR (ojo: -b -1 es el boot previo, contá bien cuál querés)
journalctl --list-boots

# memoria: mirar 'available', NO 'used'. buff/cache es caché reclamable.
free -h

# quién consume
docker stats --no-stream --format '{{.MemUsage}}\t{{.Name}}' | sort -rh

# contenedores sin techo de memoria
for id in $(docker ps -q); do
  [ "$(docker inspect $id --format '{{.HostConfig.Memory}}')" = "0" ] && docker inspect $id --format '{{.Name}}'
done
```

### Señales de alarma

| Señal | Umbral |
|---|---|
| `available` | por debajo de 500 Mi |
| `swap` | creciendo con las horas |
| `load average` (2 vCPU) | sostenido por encima de 2 |
| OOM killer | cualquier aparición |

### Limpieza de disco segura

```bash
docker builder prune -af     # cache de build, 100% descartable
docker container prune -f    # contenedores detenidos
docker image prune -af       # imágenes sin uso (el próximo deploy reconstruye)
```

**Nunca `docker volume prune`.** Ahí viven los datos.

---

## 14. Bugs conocidos del repo

### `docker/legacy-migrate.sh:58`

```bash
php artisan migrate --force >>"$OUT" 2>&1 || true
```

El `|| true` se traga el error. **Si una migración falla, el script sigue y el
deploy queda en verde con la base incompleta.** Al 31/08/2026 no llegó a morder
—los cinco tenants tienen sus 42 migraciones— pero es una bomba de tiempo.
Debería cortar y avisar.

### Instancia `cmoon` base

Tiene 43 registros en `migrations` y 65 tablas, contra 42 y 66 de los tenants.
Es la instancia de desarrollo con 25 batches de deploys encima. No afecta a
ningún cliente, pero conviene mirarlo si alguna vez se usa como plantilla.

---

## Apéndice — resumen de lo que se hizo el 31/08/2026

| Área | Antes | Después |
|---|---|---|
| Adminer | 9 dominios públicos, 2 sirviendo login sin auth | 0 dominios, contenedores sin ruta |
| Límites de memoria | ninguno en ningún servicio | los 6 servicios con techo |
| MySQL | sin tuning, OOM-killed 3 veces en una semana | buffer pool fijo + perf_schema off |
| Panel Dokploy | `http://72.60.0.249:3000`, sin cifrar | `https://dokploy.cluna.ar`, puerto cerrado |
| Swap | 3.1 GiB | ~0.5 GiB |
| Disco | 61 GB usados (63%) | 35 GB usados (36%) |
| `LEGACY_DB_DATABASE` | default `jamrod_legacy` en los 5 | default `legacy` |

Backups de las cinco bases del 31/08/2026 en `~/backups-posmoon/2026-08-31/`.
