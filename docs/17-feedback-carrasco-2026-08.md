# Feedback Carlos Carrasco (5/8/2026) — implementación cmoon

Backlog de WhatsApp implementado en el repo **cmoon original** (no Jamrod).

## Hecho

| # | Ítem | Notas |
|---|------|--------|
| 1 | Multi-caja en POS | Selector si hay 2+ sesiones abiertas del usuario; persiste en session |
| 2 | Productos: total + paginador | Contador “N productos · mostrando X–Y” |
| 3 | Padrón AFIP | MVP `PadronAfipService` + botón AFIP en form cliente (requiere `ws_sr_padron_a5`) |
| 4 | Filtro clientes POS | Combobox con búsqueda |
| 5 | Ticket fiscal | `facturacion/ticket` 80mm + botones en factura/venta/POS |
| 6 | Factura B IVA | Sin neto/IVA en cuerpo; Ley 27.743 + línea IVA. A sin cambios |
| 7 | CC → detalle venta | Link en concepto si referencia es Venta |
| 8 | Alta cliente desde POS | Modal + `POST /pos/clientes` |
| 9 | NC A/B/C | Ya existía (`facturacion.nota`) |
| 10 | Timezone | Default BA; POS ya no manda `toISOString`; Docker `php.ini` + `TZ` |
| 11 | Errores AFIP UI | Lista `observaciones` en POS |
| 12 | Eliminar caja | Soft `activa=false` si tiene historial; hard si nunca usó; bloquea si abierta |
| 13 | Informe cajas → detalle | Columna “Ver cierre” → `cajas.sesion` |
| 14 | Auditoría productos | Tabla `producto_auditoria` + Observer |
| 15 | Precio masivo | Por categoría (% o $ fijo). Sin proveedor_id en productos |
| 16 | Anulación ↔ caja | Misma fecha sesión: impacto vía exclusión; otra fecha: nota, sin egreso hoy |
| 17 | Editar fecha venta | Permiso `ventas.editar_fecha` + form en detalle + log |

## Cómo probar

```bash
cd /ruta/cmoon
# Local
docker compose up -d --build
docker compose exec app php artisan migrate --force
# → http://localhost:8080
```

Smoke: POS (caja selector, cliente search, alta cliente, facturar error AFIP), Productos (total + precio masivo + hist.), Factura B ticket, CC link, Cajas eliminar, Informe cajas detalle, Ventas editar fecha (admin).

## Sin commit

Código listo en working tree local. **No se hizo commit** (no pedido).
