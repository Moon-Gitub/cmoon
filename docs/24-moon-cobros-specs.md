# Moon Cobros — Especificación y plan

**Fecha:** 03/11/2026 · **Rama:** `moon-cobros` · **Estado:** v1 implementada y testeada en local

Sistema de facturación y cobranza de los abonos de POSMoon con Mercado Pago. Corre en un **stack Dokploy propio** (`moon-cobros`), con su propia base de datos. Reemplaza al sistema de cobro que hoy está repartido entre la cuenta cPanel `cobrosposmooncom` y el `cabezote` de cada POS.

---

## 1. Punto de partida (relevado el 03/11/2026, solo lectura)

### 1.1 Números reales (`cobrosposmooncom_db`)

| Dato | Valor |
|---|---|
| Clientes | 46 (39 con abono > 0) |
| Abono mensual total | $394.750 |
| Clientes con deuda | **36**, por **$2.468.775** (≈ 6 meses de facturación) |
| Pagos por Mercado Pago en 12 meses | ~16, el resto son transferencias, efectivo y bonificaciones cargadas a mano |
| Preferencias de MP creadas | 2.609 para 45 pagos (se crea una en **cada carga de página** del POS) |
| Cargos de $0 en cuenta corriente | 90 (la cuota se genera aunque el abono sea 0) |
| Clientes bloqueados | 0 (con 36 deudores: el bloqueo no se está aplicando en la práctica) |

**Conclusión:** el problema principal no es técnico sino de **cobranza**. Pagar hoy es difícil (hay que entrar al POS, ser admin y ver el modal), nadie recibe un aviso con un link y la deuda se acumula sin seguimiento.

### 1.2 Lo que ya funciona y se conserva (blindaje del 09/08/2026)

- Un pago solo se acredita si la **API de MP** lo confirma `approved`.
- Hay un **único punto de escritura** y la idempotencia la garantiza la base (UNIQUE sobre la referencia del pago).
- Se acredita `transaction_amount`, el importe real.
- Conciliación diaria contra MP y auditoría de cada intento.

### 1.3 Problemas que quedan

1. **Cuota mensual no idempotente:** `clientes_generar_cuotas.php` corre por cron el día 1. Si corre dos veces, cobra dos veces, y genera cargos de $0.
2. **Reglas de mora inconsistentes:**
   - El código aplica +10 % del día 11 al 20, +15 % del 21 al 26 y bloquea después del 26.
   - El texto del modal promete 3 % y 5 %.
   - El recargo se recalcula según el día en que se mira la pantalla.
3. **Credenciales en el código fuente:** access tokens de MP y la contraseña de la base central están en el código de `demonew`, replicado en 42 cuentas.
4. **Dos cuentas de MP distintas:** el `cabezote` y la función de acreditación usan tokens de cuentas distintas (collector `…1188183100` contra `…2916747261`). Hay que confirmar cuál es la oficial.
5. **Sin avisos proactivos:** no hay mail ni WhatsApp. El cliente se entera solo si entra al POS como administrador.
6. **Sin panel de cobranza:** no hay aging, ni tasa de cobro, ni forma de dar prórrogas o registrar transferencias con trazabilidad.
7. **Los tenants Laravel (cmoon)** leen la base central de cPanel directo por MySQL (`MoonCobroService`). Es frágil y expone credenciales.

---

## 2. Objetivos

| # | Objetivo | Métrica de éxito |
|---|---|---|
| O1 | Pagar en ≤ 2 clics desde el celular, sin login | Link `pagos.posmoon.com.ar/pagar/{token}` → Mercado Pago |
| O2 | Nunca acreditar un pago que no existe; nunca perder uno real | Conciliación diaria con 0 diferencias |
| O3 | Avisos automáticos que cobren solos | % de cobro del mes ≥ 85 % antes del día 15 |
| O4 | Reglas de mora claras, únicas y configurables | Una sola tabla de reglas (`config/billing.php`) |
| O5 | Control total del administrador | Panel con deuda, aging, pagos manuales, prórrogas y auditoría |
| O6 | No romper nada existente | Tenants sin cambios hasta activar `MOON_BILLING_URL`; el legacy sigue en paralelo |

---

## 3. Arquitectura

```
                       ┌────────────────────────────── Stack Dokploy "moon-cobros" ───┐
 Cliente (celular) ───▶│ app (Laravel cmoon, BILLING_HUB_ENABLED=true)                │
  /pagar/{token}       │   ├─ Página de pago pública   /pagar/{token}                 │
                       │   ├─ Webhook MP (firma HMAC)   /webhooks/mercadopago ──┐     │
 Mercado Pago ────────▶│   ├─ API tenants (Bearer)      /api/billing/v1/estado  │     │
                       │   └─ Panel admin (superadmin)  /cobros                 │     │
                       │ queue  ── ProcessMercadoPagoNotification ◀─────────────┘     │
                       │ scheduler ── cuotas · conciliación · pendientes · avisos     │
                       │ mysql (base moon_cobros) · redis                             │
                       └──────────────────────────────────────────────────────────────┘
        ▲                                   ▲
        │ GET estado (cache 10 min,         │ import legacy (solo lectura, idempotente)
        │ fail-open 72 h)                   │
 Tenants cmoon (Dokploy)          cobrosposmooncom_db (cPanel, legacy)
```

**Decisiones de diseño:**

- **Mismo repo y misma imagen que cmoon.** Se reutilizan auth, colas, mail, YCloud y Docker. El módulo se activa con `BILLING_HUB_ENABLED=true`, y solo así se cargan sus rutas y migraciones (`database/migrations/billing`). **Los tenants no reciben tablas nuevas.**
- **Base propia (`moon_cobros`).** No comparte nada con los tenants. El legacy se lee en solo lectura.
- **Libro mayor inmutable.** La cuenta corriente es una tabla de movimientos con importe firmado (+ cargo, − pago). Nunca se hace UPDATE ni DELETE: las correcciones son asientos de ajuste. Saldo = `SUM(importe)`.
- **Dinero en centavos (BIGINT).** Sin floats.
- **Estado de cobranza calculado, no guardado.** El nivel (al día / aviso / recargo / por suspender / suspendido) se calcula en cada consulta a partir del saldo, la fecha y la política. Si el cliente paga, queda habilitado al instante, sin crons que se olviden de desbloquear.
- **Fail-open en los tenants.** Si el hub no responde, el POS sigue vendiendo con el último estado conocido (72 h). Un problema del sistema de cobro nunca deja a un comercio sin vender.

---

## 4. Modelo de datos (`database/migrations/billing`)

| Tabla | Propósito | Claves de integridad |
|---|---|---|
| `billing_customers` | Cliente Moon (comercio) | `legacy_id` UNIQUE, `pay_token` UNIQUE, `api_key_hash` UNIQUE |
| `billing_subscriptions` | Conceptos recurrentes (abono, sucursal extra, IA…) con vigencia | — |
| `billing_invoices` | Cuota de un período | **UNIQUE (`subscription_id`, `periodo`)** → generar dos veces no duplica |
| `billing_ledger_entries` | Libro mayor inmutable | **UNIQUE `referencia`** (`mp:123`, `cuota:5`, `recargo:7:2026-11`, `legacy:4410`…) |
| `billing_payments` | Pagos de cualquier medio | **UNIQUE (`proveedor`, `external_id`)** |
| `billing_checkouts` | Preferencias de MP emitidas | `external_reference` UNIQUE (`MB-{uuid}`); se reutilizan |
| `billing_webhook_events` | Toda notificación recibida, cruda | `firma_valida`, `procesado_at`, `intentos`, `error` |
| `billing_notifications` | Avisos enviados | **UNIQUE (`customer_id`, `tipo`, `periodo`, `canal`)** → nunca dos veces el mismo aviso |
| `billing_audit_logs` | Auditoría de toda acción (sistema o admin) | — |
| `billing_reconciliations` | Resultado de cada conciliación | — |

---

## 5. Reglas de negocio (`config/billing.php`, todas por env)

| Regla | Valor por defecto | Variable |
|---|---|---|
| Generación de cuota | Día 1, 04:00 (ART) | — |
| Vencimiento | Día 10 | `BILLING_DIA_VENCIMIENTO` |
| Recargo tramo 1 | +10 % desde el día 11 | `BILLING_RECARGO_T1_DIA`, `BILLING_RECARGO_T1_PCT` |
| Recargo tramo 2 | +15 % desde el día 21 | `BILLING_RECARGO_T2_DIA`, `BILLING_RECARGO_T2_PCT` |
| Suspensión | Día 26 con saldo > tolerancia | `BILLING_DIA_SUSPENSION` |
| Tolerancia | $100 (diferencias de redondeo no suspenden) | `BILLING_TOLERANCIA` |

- **Base del recargo:** solo la cuota del período en curso, nunca deuda vieja (sin interés compuesto). Se aplica **una vez por período** (referencia única `recargo:{cliente}:{periodo}`), y solo a clientes con `aplicar_recargos = 1`.
- **Deuda de períodos anteriores** > tolerancia ⇒ suspendido aunque sea día 1 a 25. Es la misma semántica "pegajosa" del legacy.
- **Excepciones por cliente:**
  - `prorroga_hasta` (fecha): no se suspende hasta esa fecha.
  - `modo_cobro = solo_aviso`: nunca bloquea, solo avisa. **Es el valor por defecto al importar**, para no bloquear de golpe a los 36 deudores.
  - `modo_cobro = bloqueo`: bloquea según las reglas.
- **El tenant solo bloquea si además tiene `MOON_BILLING_ENFORCE=true`.** Son dos llaves.

### 5.1 Cronograma de avisos (mail y WhatsApp, idempotente por período)

| Día | Aviso | Contenido |
|---|---|---|
| 1 | `cuota_emitida` | Cuota del mes + link de pago |
| 8 | `recordatorio` | "Vence el 10" + link |
| 15 | `vencido` | Saldo con recargo + link |
| 22 | `por_suspender` | "El 26 se suspende" + link (solo `modo_cobro = bloqueo`) |
| Al acreditar | `recibo` | Comprobante del pago |

---

## 6. Flujo de pago (lo que vive el cliente)

1. Recibe el WhatsApp o el mail, o toca **"Pagar"** en el aviso del POS.
2. Abre `https://pagos.posmoon.com.ar/pagar/{token}` (sin login, mobile first). Ve su nombre, el detalle de cuotas adeudadas, el recargo si corresponde y el total.
3. Elige **"Pagar todo"** o **"Pagar solo este mes"**. Pasa a Mercado Pago (Checkout Pro: tarjeta, débito, dinero en cuenta, Rapipago/Pago Fácil).
4. MP lo devuelve a `/pagar/{token}/resultado`. La página **no confía en la URL**: verifica el pago contra la API y muestra el resultado real. Si todavía no está aprobado, consulta cada 3 s.
5. Al aprobarse, muestra el recibo y **redirige solo a su POS** con `location.replace`, para que no pueda volver a la URL de pago.
6. Si cierra el navegador antes, **el webhook acredita igual**. Si el webhook falla, el barrido de pendientes (cada 15 min) y la conciliación diaria lo levantan.
7. También ve los datos de transferencia (alias/CBU) como alternativa. Las transferencias las registra el admin desde el panel.

### 6.1 Preferencias

Se **reutiliza** un checkout abierto si tiene el mismo cliente, el mismo importe y no venció (TTL 48 h). Así se terminan las 2.609 preferencias basura. Cada preferencia lleva:

- `external_reference = MB-{uuid}`
- `notification_url` del hub
- `back_urls` y `auto_return = approved`
- `expires` y `statement_descriptor = MOON POS`
- `metadata.customer_id`
- el header `X-Idempotency-Key`

---

## 7. Acreditación (el corazón, `PaymentProcessor`)

Entradas: webhook, retorno del navegador, barrido de pendientes, conciliación. **Todas llaman al mismo método.**

1. `GET /v1/payments/{id}` con el token del servidor. Si falla: error, se reintenta (job con backoff 1/5/15/60 min).
2. Validaciones de rechazo; ante cualquiera de estas **no se acredita** y se audita:
   - `collector_id` ≠ cuenta Moon
   - `currency_id` ≠ ARS
   - `live_mode = false` en producción
   - `external_reference` sin checkout conocido
3. `status ≠ approved`: se guarda o actualiza en `billing_payments` y no se acredita.
4. `approved`: en una transacción, con `lockForUpdate` del cliente:
   - INSERT `billing_payments` (UNIQUE proveedor + external_id)
   - si el checkout incluía recargo: asiento `+recargo` (referencia única por período; si ya existía, el excedente queda como saldo a favor)
   - asiento `−transaction_amount` (referencia `mp:{id}`)
   - checkout → `pagado`
5. Si salta una violación de UNIQUE: `duplicado_ignorado`. Es el caso esperado cuando el webhook y el retorno llegan juntos.
6. Si el importe pagado ≠ el del checkout: **se acredita lo realmente cobrado** y queda una alerta en auditoría.
7. **Reversos:** `refunded`, `charged_back` o `cancelled` sobre un pago ya acreditado generan un asiento `+importe` (`mp-reverso:{id}`), una sola vez, con alerta.
8. Al final: se envía el recibo (aviso `recibo`) y se invalida el cache de estado.

### 7.1 Webhook

- `POST /webhooks/mercadopago` (fuera de CSRF).
- Valida `x-signature` con HMAC-SHA256 sobre `id:{data.id};request-id:{x-request-id};ts:{ts};` y el secret de la app, con tolerancia de 10 min sobre `ts`.
- Firma inválida: **401** y se guarda el evento con `firma_valida = 0`, para auditar ataques.
- Firma válida: se guarda el evento, se encola el job y se responde 200 de inmediato.
- Acepta también el formato IPN viejo (`?topic=payment&id=`). `merchant_order` → consulta la orden y procesa sus pagos.

### 7.2 Conciliación (07:00, `billing:reconcile --dias=3`)

- `GET /v1/payments/search` por rango de fechas, de las páginas necesarias.
- Pago `approved` con `MB-` no acreditado: **se acredita** y se informa.
- Asiento `mp:{id}` cuyo pago en MP no está `approved`: **alerta roja** (nunca borra sola).
- Importes que no coinciden: alerta.
- Resultado guardado en `billing_reconciliations`, con mail o Slack al admin si hay diferencias.

---

## 8. API para tenants (cmoon)

`GET /api/billing/v1/estado` con `Authorization: Bearer {api_key}`. La key es por cliente y solo se guarda su hash SHA-256.

```json
{
  "cliente": "JAMROD", "nivel": "recargo", "bloquear": false,
  "saldo": 8250.00, "mensaje": "Abono vencido. Total con recargo: $8.250",
  "pay_url": "https://pagos.posmoon.com.ar/pagar/abc…", "consultado_at": "2026-11-12T10:00:00-03:00"
}
```

En el tenant, `MoonCobroService::evaluarLicencia()` usa el hub si `MOON_BILLING_URL` y `MOON_BILLING_KEY` están configurados:

- cache de 10 min
- último estado bueno guardado 72 h
- fail-open

El layout muestra un **banner** a los administradores con el botón "Pagar ahora" cuando el nivel no es `al_dia`.

---

## 9. Panel de administración (`/cobros`, solo superadmin)

- **Tablero:**
  - facturación del mes y cobrado del mes
  - tasa de cobro
  - deuda total
  - aging (0-30 / 31-60 / 61-90 / +90)
  - clientes por nivel
  - últimos pagos
  - alertas (webhooks con error, última conciliación)
- **Clientes:** buscador, saldo, nivel, modo, botón de copiar el link de pago y botón de WhatsApp con el link prellenado.
- **Ficha del cliente:**
  - libro mayor
  - pagos
  - avisos enviados
  - registrar pago manual (transferencia, efectivo, bonificación) con referencia única
  - ajuste (+/−) con motivo obligatorio
  - prórroga
  - modo de cobro
  - cambio de abono
  - regenerar link o API key
  - todo auditado con usuario e IP
- **Pagos, Eventos (webhooks) y Conciliaciones.**

---

## 10. Seguridad

- **Secretos solo en el Environment de Dokploy:**
  - `MP_ACCESS_TOKEN`, `MP_WEBHOOK_SECRET`, `MP_COLLECTOR_ID`
  - credenciales de la base legacy
  - `YCLOUD_API_KEY`
  - nunca en el repo
- Webhook firmado con HMAC y tolerancia temporal. API de tenants con hash de la key y rate limit (`throttle:60,1`). Página pública con rate limit (`throttle:30,1`).
- El token de pago es aleatorio (40 caracteres) y solo permite **ver el saldo y pagar**. Es regenerable.
- Panel con auth existente, middleware `superadmin` y 2FA (ya existe en `security-hardening`).
- **Acción pendiente fuera de este repo:** rotar los access tokens de MP y la contraseña de `cobrosposmooncom_db` que hoy están hardcodeados en `demonew` (42 cuentas).

---

## 11. Plan de puesta en marcha

| Fase | Qué | Riesgo | Reversible |
|---|---|---|---|
| F0 | Rama `moon-cobros`, tests en verde (hecho) | — | — |
| F1 | Crear stack Dokploy `moon-cobros` (compose `docker-compose.billing.yml`, límites de memoria) + dominio `pagos.posmoon.com.ar` | Bajo: stack nuevo, no toca tenants | Borrar stack |
| F2 | `billing:import-legacy` desde `cobrosposmooncom_db` (solo lectura) y validar que los saldos coincidan 1 a 1 con el legacy | Nulo sobre el legacy | Truncar base hub |
| F3 | Configurar webhook en la app de MP (URL hub + secret), pagos de prueba con usuario de prueba, luego un pago real chico | Bajo | — |
| F4 | **Convivencia:** el legacy sigue cobrando; el hub reimporta cada hora (`billing:import-legacy` es incremental) y envía avisos con su link de pago | Bajo | Apagar `BILLING_DUNNING_ENABLED` |
| F5 | **Corte:** apagar el cron de cuotas de cPanel, `BILLING_GENERAR_CUOTAS=true` en el hub, el `cabezote` legacy redirige a `/pagar/{token}` (un archivo en las 42 cuentas, con backup) | Medio | Volver el cron y el archivo |
| F6 | Tenants cmoon: `MOON_BILLING_URL` y `MOON_BILLING_KEY` (sin enforce) → banner. Luego enforce por cliente | Bajo | Quitar variables |

**Regla:** antes de F5 se hace un backup de `cobrosposmooncom_db` y se baja fuera del servidor (regla `posmoon-backup-antes`).

---

## 12. Pruebas de aceptación (automatizadas en `tests/Feature/Billing`)

| Caso | Esperado |
|---|---|
| Pago aprobado vía webhook | 1 asiento −importe, recibo, cliente al día |
| Webhook + retorno + conciliación del mismo pago | 1 solo asiento; el resto `duplicado_ignorado` |
| Firma de webhook inválida o vencida | 401, no se procesa, evento guardado |
| Pago `pending` / `rejected` | 0 asientos; `pending` se acredita cuando pasa a `approved` |
| `collector_id` ajeno / moneda distinta / referencia desconocida | 0 asientos, auditado |
| URL de retorno adulterada | 0 asientos |
| Reembolso de un pago acreditado | 1 asiento de reverso, una sola vez |
| Generar cuotas dos veces el mismo mes | 1 cuota por suscripción; abono 0 no genera |
| Recargos por día (5, 12, 22) y exento | 0 / 10 % / 15 % / 0, una vez por período |
| Suspensión día 26, deuda vieja, prórroga, `solo_aviso` | Nivel correcto en cada caso |
| Checkout repetido | Reutiliza la preferencia (1 sola llamada a MP) |
| Conciliación con un pago no acreditado | Lo acredita y lo reporta |
| Import legacy dos veces | Mismos saldos, sin duplicados |
| API tenant con key válida / inválida | 200 con estado / 401 |
| Avisos el mismo día dos veces | 1 envío por tipo y período |
