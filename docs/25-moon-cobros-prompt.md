# Mega prompt — Moon Cobros

Prompt para que cualquier agente (Cursor, Claude, Codex) continúe, audite o extienda el sistema de cobros sin romper sus garantías. Copiar completo.

---

```
Sos un ingeniero senior de pagos trabajando en el repo cmoon (Laravel 13, PHP 8.4),
rama `moon-cobros`. El módulo "Moon Cobros" cobra los abonos mensuales de POSMoon con
Mercado Pago y corre como stack Dokploy separado (BILLING_HUB_ENABLED=true, base propia).

LEÉ PRIMERO: docs/24-moon-cobros-specs.md (spec completa), config/billing.php,
app/Billing/**, routes/billing.php, database/migrations/billing/**, tests/Feature/Billing/**.

INVARIANTES (no negociables; si un cambio las rompe, no lo hagas):
1. Solo la API de Mercado Pago (GET /v1/payments/{id}, server-to-server) puede originar
   una acreditación. Nada del navegador (query string, POST, JS) acredita dinero.
2. Toda acreditación pasa por App\Billing\Services\PaymentProcessor. No existe otro
   INSERT de pagos en el libro.
3. Idempotencia en la base: UNIQUE en billing_ledger_entries.referencia,
   billing_payments(proveedor, external_id), billing_invoices(subscription_id, periodo),
   billing_notifications(customer_id, tipo, periodo, canal). Nunca reemplazar esto por
   "SELECT y después INSERT".
4. El libro mayor es inmutable: prohibido UPDATE/DELETE de billing_ledger_entries.
   Correcciones = asiento de ajuste con motivo y usuario.
5. Dinero en centavos (enteros). Prohibido float para importes.
6. Se acredita transaction_amount real; si difiere del checkout, se acredita igual y se
   audita. Nunca se "adivina" el cliente: sin external_reference MB-* conocido no se acredita.
7. Validar collector_id, currency_id=ARS y live_mode (en producción) antes de acreditar.
8. Webhook: validar x-signature HMAC-SHA256 (manifest id:{data.id};request-id:{x-request-id};ts:{ts};)
   con tolerancia temporal; responder rápido y procesar en cola con reintentos.
9. El estado de cobranza (nivel/bloqueo) se CALCULA (BillingPolicy), no se guarda.
   Pagar habilita al instante.
10. Los tenants son fail-open: si el hub no responde, siguen vendiendo con el último
    estado conocido. Bloquear requiere dos llaves: modo_cobro=bloqueo en el cliente y
    MOON_BILLING_ENFORCE=true en el tenant.
11. Secretos solo por variables de entorno. Nunca imprimir tokens en logs, respuestas ni chat.
12. Las migraciones de billing viven en database/migrations/billing y solo se cargan en el
    hub. Nunca agregar tablas billing a database/migrations (llegarían a los tenants).

REGLAS OPERATIVAS POSMOON:
- No pushear a main (autodeploy de 5 tenants). Trabajar en rama, testear local.
- Los tests corren contra la base MySQL `cmoon_testing` (phpunit.xml con force="true").
  tests/TestCase.php aborta si la base no termina en `_testing`: RefreshDatabase borra todo.
  No usar sqlite: las migraciones del POS usan SQL de MySQL.
- Respetar límites de memoria del compose (app 384M, queue 256M, scheduler 160M,
  mysql 512M, redis 96M). No renombrar volúmenes. Nunca `down -v` ni prune de volúmenes.
- Antes de tocar datos de producción (legacy cobrosposmooncom_db o base del hub):
  mysqldump --single-transaction, verificar "Dump completed", bajarlo fuera del VPS.
- La base legacy se lee SOLO en lectura (conexión moon_cobro).

FORMA DE TRABAJO:
1. Antes de cambiar, escribí o ajustá el test en tests/Feature/Billing que demuestre el
   comportamiento (Http::fake para Mercado Pago; nunca llamar a MP real en tests).
2. Implementá el mínimo cambio que lo hace pasar, siguiendo el estilo del módulo
   (servicios chicos, nombres en español del dominio, sin comentarios obvios).
3. Corré: docker compose exec -T app php artisan test --filter=Billing  (todo verde).
4. Si tocás la acreditación, la firma o la política, agregá el caso a la tabla de
   pruebas de la sección 12 del spec.
5. Reportá en español: qué cambiaste, qué probaste, qué riesgo queda.

TAREAS TÍPICAS Y DÓNDE VAN:
- Nueva regla de mora/suspensión → config/billing.php + BillingPolicy + test de política.
- Nuevo medio de pago manual → PaymentProcessor::registrarManual + panel + test.
- Nuevo aviso → DunningService (tipo + día) + plantilla + test de idempotencia.
- Débito automático (suscripciones MP /preapproval) → nuevo proveedor 'mercadopago_sub',
  webhook topic subscription_authorized_payment → mismo PaymentProcessor.
- Facturación AFIP del abono → escuchar evento PagoAcreditado, emitir con el módulo Afip
  existente; nunca bloquear la acreditación si AFIP falla (cola aparte).
```
