<?php

namespace App\Observers;

use App\Models\Venta;
use App\Services\N8nService;

class VentaObserver
{
    public function created(Venta $venta): void
    {
        app(N8nService::class)->emitir((int) $venta->empresa_id, 'venta.creada', [
            'id' => $venta->id,
            'numero' => $venta->numero,
            'total' => $venta->total,
                    'origen' => $venta->origen,
                    'tipo' => $venta->tipo,
                    'cliente_id' => $venta->cliente_id,
        ]);
    }
}
