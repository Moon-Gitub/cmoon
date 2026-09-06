# Mejoras Gauchada → POSMoon (sep 2026)

Implementación MVP de las 3 fases del plan competitivo vs [gauchada.app](https://gauchada.app/).

## Fase 1
- Alertas en dashboard (stock bajo, CC vencida, cajas abiertas >16h, cheques)
- Agenda de cobranzas `/cobranzas`
- Asistente IA con contexto de gestión real
- Vencimiento en movimientos de CC + `clientes.dias_credito`
- Email de factura desde comprobante
- Export CITI ventas `/informes/citi-ventas`

## Fase 2
- Cheques (cartera / estados)
- Órdenes de compra + recepción parcial + OCR texto→ítems
- Remitos desde presupuesto
- Visor MercadoPago (ventas QR / config)

## Fase 3
- Cuentas bancarias + import CSV extractos
- CRM liviano (oportunidades por etapa + actividades)
- Tabla `facturas_recurrentes` (base; scheduler pendiente)

## Fuera de alcance (PRD)
Contabilidad general y RRHH/sueldos.

## Migración
`php artisan migrate` → `2026_09_06_100000_gauchada_fases_mejoras.php`
