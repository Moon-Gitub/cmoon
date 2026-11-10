# Mega prompt — Identidad visual Moon Desarrollos en Moon Cobros

Prompt para implementar `docs/27-moon-cobros-visual.md` sin reinventar ni volver al skin NVIDIA.

---

```
Sos el design+front lead de Moon Desarrollos trabajando en el repo cmoon (Laravel 13),
rama `moon-cobros`. Vas a aplicar la identidad visual OFICIAL al hub Moon Cobros.

LEÉ PRIMERO (en este orden):
1. docs/27-moon-cobros-visual.md  ← contrato de color, tipo, logo, layout, aceptación
2. docs/24-moon-cobros-specs.md   ← producto (no lo reescribas)
3. docs/26-moon-cobros-operacion.md
4. public/brand/moon-desarrollos-referencia.jpg
5. resources/css/app.css (borrar bloque .nv)
6. resources/views/billing/** , layouts/app.blade.php, auth/login.blade.php
7. app/Http/Middleware/SecurityHeaders.php (font-src 'self' — fonts self-hosted)

OBJETIVO
Que https://pagos.posmoon.com.ar se vea Moon Desarrollos: slate #4F658A, tinta #E7F6FF,
hairlines, tipografía Jost + Source Sans 3, mark SVG nuevo. Cero estética NVIDIA
(#76b900, fondo negro, pastillas verdes). Cero look “tablero hecho por IA”
(5 KPI cards idénticas, glow, rounded-full, indigo).

INVARIANTES DE PRODUCTO (no tocar)
- Ledger inmutable, PaymentProcessor único, flags BILLING_* / MOON_BILLING_ENFORCE
- No merge a main; deploy solo compose hub Vi0nAji_Ctg30yORqI7iv
- No imprimir secrets; no docker compose down -v
- Tenant POS no se redeployan por este cambio

TRABAJO
1. Crear SVGs en public/brand/ según §3 de docs/27 (mark + wordmarks). El mark debe
   superar la idea del JPG: fase lunar + constelación de 7 nodos en hairline.
2. Self-host fonts en public/fonts/ (Jost + Source Sans 3 latin woff2) y @font-face.
3. Reemplazar clase body `nv` por `md` en layouts hub + login hub + pay.
4. Reescribir tokens CSS .md; eliminar .nv.
5. Nav: underline activo, no pastilla de color.
6. Dashboard: conservar datos/interacción Alpine ya existentes; repintar con tokens MD.
7. Badges de nivel con --md-ok / --md-warn / --md-danger.
8. Botón Mercado Pago = #009EE3 únicamente en la página pública de pago.
9. Actualizar docs/26 con “skin oficial MD; NVIDIA descartado”.
10. Smoke en browser + compose.deploy del hub. Screenshots Tablero/Login/Clientes.

CRITERIOS
Checklist §9 de docs/27. Si algo no cumple, no digas “listo”.

PROHIBIDO
- Google Fonts CDN
- Reintroducir verde NVIDIA o fondo #000 como base
- Cambiar lógica de cobros / migraciones
- Push a main
```
