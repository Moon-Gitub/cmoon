<?php

return [

    /** Preguntas/mes en el plan incluido (sin abono). */
    'cupo_incluido' => (int) env('IA_CUPO_INCLUIDO', 50),

    /** Preguntas/mes con abono mensual activo. */
    'cupo_abono' => (int) env('IA_CUPO_ABONO', 500),

    /** Texto comercial (no cobra solo: lo activa un admin). */
    'abono_precio' => env('IA_ABONO_PRECIO', 'consultar'),

    'abono_contacto' => env('IA_ABONO_CONTACTO', ''),

];
