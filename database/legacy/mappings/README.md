# Mapeos campo a campo (referencia)

Ver implementación en `app/LegacyImport/Mappers/` y cada `*Importer.php`.

| Legacy (demonew) | CMoon |
|------------------|-------|
| `empresa` | `empresas` + `sucursales` + `emisores` + `puntos_venta` |
| `categorias.categoria` | `categorias.nombre` |
| `listas_precio` | `listas_precio` (porcentaje desde `valor_descuento`) |
| `medios_pago` | `medios_pago` |
| `usuarios` | `users` + Spatie roles |
| `productos` + stock/stock2/stock3 | `productos` + `stocks` |
| `combos` + `combos_productos` | `productos.es_combo` + `combo_componentes` |
| `clientes` | `clientes` |
| `ventas` + `productos_venta` | `ventas` + `venta_items` + `venta_pagos` |
| `ventas_factura` | `comprobantes` |
| `clientes_cuenta_corriente` | `movimientos_cuenta` (morph Cliente) |
| `proveedores_cuenta_corriente` | `movimientos_cuenta` (morph Proveedor) |
| `presupuestos` | `presupuestos` + `presupuesto_items` |
| `compras` | `compras` + `compra_items` |

Esquema legacy de referencia: repositorio demonew → `migracion/modelo-estructura-completa.sql`.
