# Mejoras Gauchada → POSMoon (sep 2026)

Implementación **completa** de las 3 fases del plan vs [gauchada.app](https://gauchada.app/).

## Fase 1 — lista
- Asistente IA con contexto real (ventas, CC, stock, qué pedir)
- Alertas en dashboard (stock bajo, CC vencida, cajas >16h, cheques)
- Facturas recurrentes: UI `/facturas-recurrentes` + job `facturas:recurrentes` (diario 07:00)
- Email de factura (HTML + PDF TCPDF / fallback .txt)
- Export CITI ventas + Libro IVA compras (`/informes/libro-iva-compras`)
- Agenda cobranzas `/cobranzas` + `vencimiento` / `dias_credito`

## Fase 2 — lista
- Cheques (cartera / depositado / cobrado / rechazado)
- Órdenes de compra + recepción parcial (stock)
- OCR texto → JSON → compra borrador (`compras/desde-ocr`)
- Cadena: presupuesto → **pedido** → remito → venta/factura
- Visor MercadoPago: liquidaciones + pagos API + ventas QR

## Fase 3 — lista
- Bancos: cuentas, import CSV, conciliar / desconciliar
- Depósitos ≠ sucursales + lotes/series por producto
- CRM liviano (etapas + actividades + filtro)

## Fuera de alcance (PRD)
Contabilidad general y RRHH/sueldos.

## Migraciones
```bash
php artisan migrate
# 2026_09_06_100000_gauchada_fases_mejoras
# 2026_09_06_140000_depositos_y_lotes
```

## Scheduler
Requiere el contenedor `scheduler` en Dokploy. Comando: `facturas:recurrentes`.
