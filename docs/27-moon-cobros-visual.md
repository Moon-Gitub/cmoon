# Moon Cobros — Spec visual (identidad Moon Desarrollos)

**Fecha:** 10/11/2026 · **Rama:** `moon-cobros` · **Estado:** en implementación / deploy hub  
**Reemplaza:** el skin NVIDIA (negro + `#76b900`) aplicado al hub. Ese look se descarta.  
**Fuente oficial de color:** `public/brand/moon-desarrollos-referencia.jpg` (logo Moon Desarrollos).

---

## 0. Por qué se rehace

El tablero actual (negro + verde ácido + grilla de KPIs / barras bloque) se lee como panel genérico de IA, no como Moon Desarrollos. El cliente rechazó ese estilo. La identidad oficial es:

- fondo **azul pizarra** (no negro, no púrpura, no cream)
- trazos **blancos / plateados finos**, geométricos
- tipografía limpia, tracking amplio en títulos
- una marca de luna + red (constelación), no un emoji ni un “M” en un cuadrado verde

**Alcance:** solo el hub (`BILLING_HUB_ENABLED=true`). Los POS de clientes (Jamrod, Abisko, Cabañas, Esquina53, cmoon) **no** cambian de look en este plan.

---

## 1. Paleta oficial (muestreada del logo)

Valores tomados del JPG de referencia (Pillow, esquinas y media del fondo). No inventar acentos neón.

| Token | Hex | Uso |
|---|---|---|
| `--md-slate` | `#4F658A` | Fondo de página, header, login |
| `--md-slate-deep` | `#3E5272` | Paneles hundidos, sidebar, hover fuerte |
| `--md-slate-mid` | `#5B759A` | Superficies elevadas (ligero lift sobre el fondo) |
| `--md-slate-line` | `#6A82A3` | Reglas, bordes, ejes de gráfico |
| `--md-ink` | `#E7F6FF` | Texto principal, wordmark, iconos lineales |
| `--md-ink-mute` | `#B8C6D6` | Texto secundario, labels, ejes |
| `--md-moon` | `#9096A2` | Relleno de luna / texturas |
| `--md-moon-deep` | `#444F61` | Cráteres / sombra de la luna |
| `--md-danger` | `#C45B52` | Mora, moroso, error (desaturado, no neón) |
| `--md-warn` | `#C4A35A` | Vencido / por suspender |
| `--md-ok` | `#6F8F7A` | Al día / OK (verde frío, no NVIDIA) |
| `--md-mp` | `#009EE3` | Solo el botón “Pagar con Mercado Pago” (marca ajena) |

**Prohibido en el hub:** `#76b900`, `#5c8f00`, fondo `#000`, indigo Tailwind (`#4f46e5`), purple gradients, glow, pills `rounded-full` decorativos, sombras multicapa, emojis en chrome.

Contraste texto sobre slate: `#E7F6FF` sobre `#4F658A` ≥ 4.5:1 para cuerpo. Importes grandes usan `#E7F6FF`; montos en mora usan `--md-danger`.

---

## 2. Tipografía

Self-hosted (CSP del hub: `font-src 'self' data:` — **no** Google Fonts CDN).

| Rol | Familia | Peso | Notas |
|---|---|---|---|
| Display / nav / wordmark | **Jost** | 300–500 | Geométrica, tracking alto en títulos (`letter-spacing: 0.12em`–`0.28em` en “MOON COBROS”) |
| Cuerpo / tablas | **Source Sans 3** | 400–600 | Legible en densidades de cobranzas |
| Números de dinero | Jost con `font-variant-numeric: tabular-nums` | 400–500 | Columnas alineadas |

Archivos: copiar woff2 a `public/fonts/` desde `@fontsource/jost` y `@fontsource/source-sans-3` (latín). Declarar `@font-face` en `resources/css/app.css` dentro del bloque `.md`.

---

## 3. Logo rehecho (“Moon mark”)

### 3.1 Idea de partida (referencia)

El logo actual: `M` geométrica de triángulos, `O` con textura de luna, `O` con red de nodos, `N` espejada, subtítulo `DESARROLLOS` con tracking extremo sobre slate `#4F658A`.

### 3.2 Versión hiper-superadora (entregable)

Un **monograma** nuevo, no un calco del JPG:

1. **Mark** (favicon / avatar / header): círculo fino de 64 unidades. Dentro, un **disco lunar** (arco de fase) cortado por una **constelación** de 7 nodos unidos por hairlines (la red = software + clientes). El vacío entre fase y red es el “aire” de Moon: producto + red.
2. **Wordmark hub:** `MOON` (Jost 300, tracking 0.22em) + `COBROS` (Jost 400, tracking 0.18em, color `--md-ink-mute`). Sin emoji.
3. **Wordmark institucional** (login / mail): `MOON` + debajo `DESARROLLOS` tracking 0.35em, tamaño ~28 % del wordmark.
4. Formatos: SVG vectorial en `public/brand/moon-mark.svg`, `moon-wordmark-cobros.svg`, `moon-wordmark-desarrollos.svg`. PNG 32/180 solo si hace falta PWA; el hub usa SVG.
5. El mark **nunca** va en un cuadrado de color de acento. Va en línea blanca sobre slate, o slate sobre ink en documentos impresos.

Criterio de calidad: debe reconocerse a 24 px (favicon) y a 240 px (login) sin relleno sólido grueso ni sombra.

---

## 4. Sistema de layout (anti–tablero-IA)

### 4.1 Principios

1. **Una composición, no un dashboard de cards.** El primer viewport del Tablero es: wordmark + una frase de estado + un gráfico dominante. Nada de 5 KPI boxes idénticas.
2. **Hairlines, no cards.** Separar con `1px solid var(--md-slate-line)`. Si al sacar border/shadow/radius no se pierde claridad, no hay card.
3. **Radio máximo 2 px** en inputs/botones. Casi flat.
4. **Un acento de interacción:** hover ilumina el trazo a `--md-ink`; el activo de nav es underline hairline + ink, **no** pastilla verde.
5. **Motion:** 1) entrada del gráfico (draw de barras, 400 ms); 2) crossfade del detalle al cambiar mes; 3) underline de nav. Respetar `prefers-reduced-motion`.
6. **Copy en español rioplatense**, sentence case, sin “¡Pago acreditado!” chillón en el admin.

### 4.2 Wire Tablero (ASCII)

```
┌──────────────────────────────────────────────────────────┐
│  ◯ MOON  COBROS                    Tablero Clientes …    │
├──────────────────────────────────────────────────────────┤
│  Deuda $2.468.925 · nov facturó … cobró … (15 %)         │
│                                                          │
│   ▍ ▍   ▍ ▍   …   gráfico cargos/cobros 6|12 meses       │
│   jun         …                          detalle mes →   │
│                                                          │
│  ──── antigüedad ──────────────────────── (tramos)       │
│  Moroso 37 · Al día 9 · …   |  últimos pagos (lista)     │
└──────────────────────────────────────────────────────────┘
```

### 4.3 Pantallas cubiertas

| Ruta | Tratamiento |
|---|---|
| `/login` | Full-bleed slate, mark centrado, form sin card blanca |
| `/` (Inicio hub) | Misma piel `.md`; el empty POS se puede minimizar o redirigir a `/cobros` (decidir en implementación: preferible link fuerte a Cobros) |
| `/cobros` | Spec §4.2 |
| `/cobros/clientes`, `/pagos`, `/avisos`, `/eventos`, ficha cliente | Tablas hairline, mismos tokens; badges de nivel con `--md-ok/warn/danger` |
| `/pagar/{token}` | Layout público slate; CTA MP queda `--md-mp` |
| Mails | Logo wordmark SVG/PNG inline; fondo `#4F658A` o blanco con reglas slate |

---

## 5. Tokens CSS (contrato de implementación)

Body del hub: clase `md` (reemplaza `nv`).

```css
.md {
  --md-slate: #4F658A;
  --md-slate-deep: #3E5272;
  --md-slate-mid: #5B759A;
  --md-slate-line: #6A82A3;
  --md-ink: #E7F6FF;
  --md-ink-mute: #B8C6D6;
  --md-moon: #9096A2;
  --md-moon-deep: #444F61;
  --md-danger: #C45B52;
  --md-warn: #C4A35A;
  --md-ok: #6F8F7A;
  --md-mp: #009EE3;
  background: var(--md-slate);
  color: var(--md-ink);
  font-family: "Source Sans 3", system-ui, sans-serif;
}
```

Reglas de mapeo (Tailwind legacy → tokens):

- `.md .bg-white` → `--md-slate-mid`
- `.md .bg-slate-50/100` → `--md-slate-deep`
- `.md .text-slate-*` → `--md-ink` / `--md-ink-mute`
- `.md .bg-indigo-*` → `--md-ink` texto sobre `--md-slate-deep` borde, o botón outline ink (no verde)
- Borrar bloque `.nv` completo de `app.css` al migrar

Layouts a tocar: `billing/admin/layout.blade.php`, `billing/layout.blade.php`, `layouts/app.blade.php` (solo si `$esHub`), `auth/login.blade.php` (rama hub).

---

## 6. Gráficos e interacción (conservar comportamiento, cambiar piel)

Se mantiene lo útil del tablero actual:

- Serie 12 meses cargos vs cobros (controller ya entrega `serie`)
- Hover/focus de mes actualiza el detalle
- Toggle 6 / 12 meses
- Barra de aging clickeable
- Filtro de deudores por nivel

Cambia el look:

- Barras: cargos `--md-slate-deep`, cobros `--md-ink` (o `--md-moon` si el contraste pide más soft)
- Aging: ok → warn → danger en desaturados de la tabla §1
- Sin pastillas verdes de nav; activo = underline

Datos: no tocar ledger, políticas ni flags (`BILLING_DUNNING`, `GENERAR_CUOTAS`, `MOON_BILLING_ENFORCE`).

---

## 7. Archivos a crear / cambiar

| Path | Acción |
|---|---|
| `public/brand/moon-desarrollos-referencia.jpg` | Referencia (ya copiada) |
| `public/brand/moon-mark.svg` | Mark nuevo |
| `public/brand/moon-wordmark-cobros.svg` | Header hub |
| `public/brand/moon-wordmark-desarrollos.svg` | Login / mails |
| `public/fonts/jost-*.woff2`, `source-sans-3-*.woff2` | Self-host |
| `resources/css/app.css` | Quitar `.nv`; agregar `.md` + `@font-face` |
| `resources/views/layouts/app.blade.php` | `md` si hub |
| `resources/views/billing/admin/layout.blade.php` | Header con SVG + nav underline |
| `resources/views/billing/admin/dashboard.blade.php` | Piel `.md`, sin clases NVIDIA |
| `resources/views/billing/layout.blade.php` + `pay/*` | Público |
| `resources/views/auth/login.blade.php` | Rama hub |
| `resources/views/billing/admin/_nivel.blade.php` | Colores ok/warn/danger |
| `docs/26-moon-cobros-operacion.md` | Nota: skin oficial MD, NVIDIA descartado |
| Tests | `assertSee('Deuda')` / rutas 200; agregar `assertSee('md')` o el wordmark si hace falta |

---

## 8. Plan de ejecución (orden)

1. **Assets:** SVGs del mark + wordmarks; fonts en `public/fonts`.
2. **CSS:** bloque `.md`; eliminar `.nv`.
3. **Layouts + login + pay.**
4. **Dashboard + resto admin** (clientes, pagos, avisos, eventos, ficha).
5. **Smoke:** login moon → `/cobros` → hover mes → filtro Moroso → `/pagar/{piloto}` CTA azul MP.
6. Commit en `moon-cobros`, `compose.deploy` solo del compose hub `Vi0nAji_Ctg30yORqI7iv`. **No** push a `main`.
7. Screenshot de Tablero, Login y Clientes para validación humana.

Estimación: 1 sesión de implementación enfocada (UI only). No requiere migración de DB.

---

## 9. Criterios de aceptación

- [ ] Cero restos de `#76b900` / clase `nv` en vistas del hub.
- [ ] Fondo dominante ≈ `#4F658A` en login, tablero y pagar.
- [ ] Logo SVG propio (no el JPG pixelado) en header y login.
- [ ] Tipografías self-hosted cargan (Network: woff2 200).
- [ ] Tablero sigue interactivo (mes, rango, aging, filtro nivel).
- [ ] Botón MP sigue azul `#009EE3`.
- [ ] POS tenants sin redeploy / sin cambio visual.
- [ ] `billing:diagnostico` y flags de cobranza intactos.

---

## 10. Fuera de alcance

- Rebrand de los POS clientes.
- Activar dunning / cuotas / enforce.
- Secret de webhook MP, SMTP, YCloud (siguen bloqueados por credenciales).
- Rediseño de mails transaccionales más allá de header con wordmark (fase 2 opcional).
