# WhatsApp (YCloud) + canales de publicación

Elegís **qué producto** se publica en cada canal. El POS interno no cambia.

| Canal | Flag | Efecto |
|-------|------|--------|
| WhatsApp | `publicar_whatsapp` | El bot responde consultas solo con esos ítems. Si hay plantilla de catálogo, se ofrece al pedir “catálogo”. |
| Shopify | `publicar_shopify` | El **push** a Shopify solo sincroniza esos productos. |
| Tiendanube | `publicar_tiendanube` | El push a Tiendanube solo sincroniza esos productos. |

## Cómo marcar productos

1. **Ficha** del producto → “Publicar en canales”.
2. **Productos → Publicar en canales**: filtro + selección masiva (página actual).

No marques 20.000 ítems de golpe: stock 0 / precio 0 / sin nombre claro ensucian WhatsApp y Shopify.

## YCloud

1. Cuenta en [ycloud.com](https://www.ycloud.com/).
2. API key + número WhatsApp Business (E.164, ej. `+54911…`).
3. En POSMoon: menú **WhatsApp** → pegar key y número.
4. En YCloud → Developers → Webhooks:
   - URL: `{APP_URL}/webhooks/ycloud`
   - Evento: `whatsapp.inbound_message.received`

El worker `queue` tiene que estar corriendo.

### Bot de consultas

- Busca en productos con `publicar_whatsapp`.
- Si hay `OPENAI_API_KEY`, redacta la respuesta (precios/stock del POS).
- Si no hay key, arma una lista con nombre, precio y stock.
- “vendedor” / “humano” → pausa el bot 2 h (handoff). En **Chats** podés reanudarlo.
- Pedir “catálogo” / “ofertas”: lista destacada; si configuraste plantilla Meta, también manda el botón de catálogo.

Probar sin WhatsApp: en la misma pantalla, **Simular respuesta**.

### Plantilla de catálogo (opcional)

En Meta/YCloud creá una plantilla MARKETING con botón `CATALOG` (“View catalog”) y pegá el nombre en la UI (`intro_catalog_offer`, etc.). El inventario de Commerce Manager de Meta es independiente: este módulo **elige qué ofrecer en el chat** desde el POS.

## API

`GET /api/v1/productos?canal=whatsapp|shopify|tiendanube`

## Variables

Ver `.env.example` y [04-variables-entorno.md](./04-variables-entorno.md). Las credenciales por empresa van en la UI (encrypted).
