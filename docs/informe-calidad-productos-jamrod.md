# Informe de calidad de datos — Productos Jamrod

**Cliente:** JAMROD (Autotransportes San Rafael S.R.L.)  
**CUIT:** 30-69943364-7  
**Sistema:** POSMoon  
**Fecha del análisis:** 30 de julio de 2026  
**Alcance:** catálogo de productos, categorías y stock de la empresa migrada  

---

## 1. Resumen ejecutivo

Tras la migración a POSMoon, el catálogo de Jamrod quedó con **23.292 productos** y **221 categorías**. La migración copió fielmente lo que había en el sistema anterior: no se inventaron datos nuevos. El resultado es un catálogo **usable para vender**, pero con varios problemas de calidad que conviene ir corrigiendo: muchos productos con el **mismo nombre** (sobre todo indumentaria por talle/color mal descripta), **categorías casi todas vacías** y la mayor parte del stock en cero, **precios de venta en cero** en cerca del 15 % de los ítems, **costo de compra vacío en el 100 %**, y unos **248 productos con stock negativo**. Ninguno de estos puntos indica un fallo de la migración en sí: son datos heredados (o incompletos) del sistema viejo que ahora se ven con más claridad.

---

## 2. Panorama general

| Indicador | Valor | % sobre el catálogo |
|-----------|------:|--------------------:|
| Productos totales (activos) | 23.292 | 100 % |
| Productos inactivos | 0 | 0 % |
| Categorías totales | 221 | — |
| Categorías con al menos 1 producto | 14 | 6,3 % de las categorías |
| Categorías vacías (sin productos) | 207 | 93,7 % de las categorías |
| Productos sin categoría | 189 | 0,8 % |
| Sucursal / depósito | 1 (“Principal”) | — |

**Nota:** Existe también una empresa vacía de plantilla (“Mi Empresa”) en el sistema; este informe analiza únicamente la empresa **JAMROD**.

---

## 3. Hallazgos

### 3.1 Productos con el mismo nombre (duplicados / variantes mal nombradas)

- **Códigos duplicados:** no se detectaron. Cada producto tiene un código único (bien).
- **Mismo nombre (ignorando mayúsculas y espacios):** **1.851 grupos** que agrupan **8.013 productos** (**34,4 %** del catálogo).

En indumentaria y ciclismo es normal tener varios talles o colores. El problema es que en muchos casos el **nombre no distingue** talle, color ni modelo, y solo cambia el código. Eso complica buscar en el POS, armar pedidos y controlar stock.

**Ejemplos:**

| Nombre que se repite | Cantidad | Observación |
|----------------------|--------:|-------------|
| Scott | 28 | Solo dice la marca; códigos distintos (NMSM… / NMSU…). Precio $0. |
| ELECTRA CALZA 7/8 NEGRO | 12 | Mismos nombre; precios distintos ($3.250 a $30.250). Parecen talles o cargas viejas. |
| H MUSCULOSA WILLIS | 11 | Variantes por código; mismo precio ($34.900). |
| H REMERA FIT XXX | 11 | Idem: nombre genérico, códigos distintos. |

**Recomendación:** completar el nombre con talle/color/modelo (ej. “Scott casco Supra blanco M”) o usar campos de variante si el flujo de trabajo lo permite. Priorizar los grupos con más repeticiones y los que se venden seguido.

---

### 3.2 Nombres y códigos mal puestos

| Problema | Cantidad | % |
|----------|--------:|--:|
| Nombre que es solo un número largo (tipo código de barras) | 26 | 0,1 % |
| Nombre igual al código | 94 | 0,4 % |
| Nombre muy largo (más de 120 caracteres) | 361 | 1,6 % |
| Código muy largo (más de 40 caracteres) | 1 | ~0 % |
| Producto con nombre genérico de categoría (“Indumentaria”) | al menos 1 | — |

**Ejemplos de nombres poco útiles para vender:**

- Código y nombre: `769493501592` — precio $0  
- Código y nombre: `12820008053845303661` — precio $0  
- Código y nombre: `L811201005BLANC` — precio $0  
- Código = descripción completa: `161536-249-02-PANTALON ARENA 2 Rip-Stop HOMBRE GRIS CEMENTO S` (el código quedó como si fuera el nombre largo del sistema anterior)  
- Producto `código 1` / nombre **“Indumentaria”** / precio $0 — parece un ítem basura o una categoría cargada como producto  

En el sistema anterior a veces el código y la descripción estaban “al revés” o mezclados. Eso se trasladó: en la mayoría de los casos el código es usable, pero hay un grupo chico donde el nombre no ayuda a identificar el artículo.

**No se observó** de forma masiva el caso extremo “nombre = solo un precio ($…)”; sí hay muchos nombres que son códigos y no una descripción comercial.

---

### 3.3 Categorías

| Situación | Cantidad |
|-----------|--------:|
| Categorías totales | 221 |
| Con productos | 14 |
| Vacías | 207 |
| Productos sin categoría | 189 |

**Concentración:** casi 4 de cada 5 productos están en una sola categoría.

| Categoría | Productos | % del catálogo |
|-----------|--------:|---------------:|
| Indumentaria | 18.213 | 78,2 % |
| MEDIAS | 1.922 | 8,3 % |
| General | 1.681 | 7,2 % |
| BLOOM | 673 | 2,9 % |
| ZAPATILLA | 318 | 1,4 % |
| Accesorios | 118 | 0,5 % |
| Resto (8 categorías) | ~367 | ~1,6 % |
| Sin categoría | 189 | 0,8 % |

**Categorías duplicadas o con errores de tipeo** (misma idea, nombres distintos):

- `ZAPATILLA` (318) y `ZAPATILLAS` (30)  
- `ACCESORIOS HIDRATACIÓN` (8) y `ACCCESORIOS HIDRATACIÓN` (33) — con triple “C”  
- `SUPLEMENTOS` (34) y `Sumplementos` (3) — typo  

**Categoría genérica “General”:** 1.681 productos; de ellos, **1.641 tienen precio $0**. Es un depósito de ítems poco clasificados.

Hay decenas de categorías con nombres útiles (ej. “Accesorios montaña”) pero **sin ningún producto asignado**: el árbol de categorías del sistema viejo se migró, pero la asignación real quedó concentrada en pocas.

---

### 3.4 Stock

| Situación | Productos | % |
|-----------|--------:|--:|
| Stock positivo | 4.119 | 17,7 % |
| Stock en cero | 18.925 | 81,3 % |
| Stock negativo | 248 | 1,1 % |
| Sin registro de stock | 0 | 0 % |
| Más de un depósito | 0 | (solo sucursal Principal) |

- Stock máximo observado: **30 unidades** (termos Elite).  
- Stock mínimo: **-7** (ej. trail running).  
- Suma total de unidades en sistema: ~**5.581**.  
- Movimientos de stock posteriores a la migración: prácticamente nulos (el stock llegó como “foto” del sistema anterior).

**Ejemplos de stock negativo:**

| Código | Nombre (resumen) | Stock |
|--------|------------------|------:|
| L751200923ROSA0 | ART. TE92C TRAIL RUNNING | -7 |
| L751400144TURQU | ART. ME14C T-POWER | -4 |
| 7798115261401 | Gel MERVICK ENERGY cafeína Naranja | -4 |
| 7798115260442 | Gel MERVICK ENERGY cafeína Manzana | -4 |
| 1550802 | VENICE BEACH Visera BLANCO | -3 |

**Interpretación para el cliente:** el 81 % en cero puede ser real (catálogo histórico amplio) o stock no mantenido en el sistema viejo. Los **negativos sí conviene corregirlos ya**: distorsionan disponibilidad y reportes.

---

### 3.5 Precios y costos

| Situación | Cantidad | % |
|-----------|--------:|--:|
| Precio de venta = $0 (todos activos) | 3.427 | 14,7 % |
| Precio de compra / costo = $0 | 23.292 | **100 %** |
| Precio de venta mayor a $1.000.000 | 21 | 0,1 % |
| Precios negativos | 0 | 0 % |

Los precios altos detectados corresponden sobre todo a **bolsas de dormir** de montaña (Antártida / Andes, en el orden de $1,6M–$2,2M). Pueden ser precios vigentes de productos premium; conviene que el equipo comercial **valide** si están correctos o si arrastran un error de carga (por ejemplo, ceros de más).

**Precio $0 concentrado en:**

- General: 1.641  
- Indumentaria: 1.414  
- Sin categoría: 186  
- MEDIAS: 157  

Sin costo de compra **no se puede** calcular margen ni valorizar stock de forma confiable.

---

### 3.6 Otros datos útiles

| Tema | Resultado |
|------|-----------|
| Marca (campo marca) | **Vacío en el 100 %** |
| Código interno | Vacío en el 100 % |
| Origen | Vacío en el 100 % |
| Proveedores cargados en el sistema | 42 (no hay vínculo producto↔proveedor en el catálogo migrado) |
| Combos / kits | 0 |
| Productos pesables (balanza) | 0 |
| Unidad | Todos en “UN” |
| Listas de precio | Ninguna cargada |

---

## 4. Recomendaciones priorizadas

### Prioridad 1 — Impacto inmediato en la venta diaria

| # | Acción | Quién | Impacto |
|---|--------|-------|---------|
| 1 | Corregir o desactivar los **3.427 productos con precio $0** que no deban venderse (empezar por “General” y sin categoría). | Administración / precios | Evita vender a $0 o bloquear la caja con ítems inútiles. |
| 2 | Ajustar los **248 stocks negativos** (poner en 0 o al valor real del depósito). | Depósito / encargado de stock | Reportes y disponibilidad coherentes. |
| 3 | Eliminar o renombrar el producto basura **“Indumentaria” (código 1)** y similares. | Administración | Limpia búsquedas en el POS. |

### Prioridad 2 — Orden del catálogo (1–3 semanas)

| # | Acción | Quién | Impacto |
|---|--------|-------|---------|
| 4 | Completar nombres de las variantes más vendidas (talle, color, modelo). Empezar por grupos como “Scott”, calzas Electra, musculosa Willis, etc. | Compras / catálogo | Búsqueda y tickets más claros. |
| 5 | Reasignar productos desde **Indumentaria / General** hacia categorías reales; unificar duplicados (`ZAPATILLA`/`ZAPATILLAS`, `SUPLEMENTOS`/`Sumplementos`, hidratación con typo). | Catálogo | Navegación y filtros útiles. |
| 6 | Completar los **189 sin categoría** y archivar las **207 categorías vacías** que no se vayan a usar. | Catálogo | Menos ruido en pantallas. |

### Prioridad 3 — Gestión y márgenes (continuo)

| # | Acción | Quién | Impacto |
|---|--------|-------|---------|
| 7 | Cargar **costo / precio de compra** (aunque sea por familia o proveedor principal). | Compras / administración | Márgenes y valorización de stock. |
| 8 | Completar **marca** (y proveedor cuando corresponda) en los ítems activos con movimiento. | Compras | Filtros, reposiciones e informes. |
| 9 | Validar los **21 precios > $1M** (bolsas de dormir y similares). | Comercial | Evitar errores de facturación. |
| 10 | Definir qué hacer con el **81 % en stock 0**: ¿catálogo histórico a inactivar? ¿solo dejar activos los que se reponen? | Dirección + depósito | Catálogo más liviano y usable. |

---

## 5. Nota sobre la migración

Los datos revisados son los **heredados del sistema anterior**. La migración a POSMoon **copió lo que había** (productos, categorías y stock disponible). Los problemas de nombres incompletos, categorías vacías, costos en cero y stocks negativos **ya existían o quedaron expuestos** al pasar a un sistema más ordenado; no se trata de “productos inventados” por el proceso de migración.

Cualquier limpieza (precios, nombres, categorías, stock) se puede hacer desde POSMoon de forma gradual, empezando por lo que más se vende día a día.

---

## 6. Próximo paso sugerido

1. Reunión breve (30–45 min) con quien maneja precios y depósito.  
2. Acordar: lista de productos a **inactivar**, corrección de **stock negativo**, y plan de **nombres/categorías** de las 20 familias más vendidas.  
3. En una segunda etapa: costos y marcas.

Si lo desean, el equipo técnico puede entregar listados exportables (Excel) de cada hallazgo para trabajarlos en planta.

---

*Informe generado a partir de consulta de solo lectura sobre la base de datos de producción Jamrod en POSMoon. Cifras redondeadas al decimal indicado; porcentajes calculados sobre 23.292 productos activos.*
