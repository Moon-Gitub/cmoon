# n8n + chat IA con cupo mensual

## n8n

POSMoon **emite** eventos a Webhook nodes de n8n y **recibe** llamadas HTTP de esos flujos.

### POSMoon → n8n

En **n8n** (menú): pegá la *Production URL* de cada Webhook (workflow publicado, no `/webhook-test/`).

| Evento | Cuándo |
|--------|--------|
| `venta.creada` | Se cierra una venta |
| `producto.actualizado` | Cambian datos/canales de un producto |
| `whatsapp.inbound` | Consulta WhatsApp (si hay bot) |
| `asistente.mensaje` | Pregunta en el chat del panel |
| `custom` | Botón “Enviar ping” |

Header Auth opcional (`X-N8N-Auth` o el que uses en el nodo Webhook).

Payload:

```json
{
  "event": "venta.creada",
  "empresa_id": 1,
  "occurred_at": "2026-08-12T01:00:00-03:00",
  "data": { "id": 10, "numero": 102, "total": 15000 }
}
```

### n8n → POSMoon

`POST {APP_URL}/webhooks/n8n`

Headers: `X-N8N-Secret: <inbound_secret>`  
Body JSON:

```json
{ "empresa_id": 1, "accion": "ping" }
```

Acciones: `ping`, `productos.buscar` (`q`), `whatsapp.enviar` (`to`, `body`).

También podés usar la **API REST v1** (`/api/v1/...` + Sanctum) desde un nodo HTTP Request.

## Chat IA (cupo)

- Menú **Asistente IA**.
- Plan **incluido**: 50 preguntas/mes (configurable `IA_CUPO_INCLUIDO`).
- **Abono**: 500/mes (`IA_CUPO_ABONO`) hasta `ia_abono_hasta`.
- **Créditos extra**: paquetes Básico/Pro/Mega que el cliente solicita desde el Asistente; el **supermegaadmin** los aprueba en `/admin/ia` (o acredita a mano).
- El bot de WhatsApp **también consume** el mismo cupo.
- Si se acaba: mensaje de límite + compra de paquetes / “Quiero más preguntas”.

### Supermegaadmin (`/admin/ia`)

Solo usuarios con `users.es_superadmin = 1` (o `SUPERADMIN_EMAIL` al migrar). Ahí se configura:

- Proveedor: OpenAI, OpenRouter, Groq o custom (compatible OpenAI)
- API key / modelo / base URL (DB; fallback a `OPENAI_*` del `.env`)
- Cupo override, plan abono y acreditación de créditos por empresa
- Aprobación de compras y edición de paquetes

Los admins de tenant **no** ven API keys ni el panel; solo saldo y compra de créditos.

Variables: `.env.example` y [04-variables-entorno.md](./04-variables-entorno.md).
