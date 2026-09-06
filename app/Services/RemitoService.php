<?php

namespace App\Services;

use App\Models\Presupuesto;
use App\Models\Remito;
use App\Models\RemitoItem;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class RemitoService
{
    public function crearDesdePresupuesto(Presupuesto $presupuesto): Remito
    {
        $presupuesto->loadMissing('items');

        if ($presupuesto->items->isEmpty()) {
            throw new InvalidArgumentException('El presupuesto no tiene ítems.');
        }

        return DB::transaction(function () use ($presupuesto) {
            $empresaId = (int) $presupuesto->empresa_id;
            $numero = (int) Remito::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->max('numero') + 1;

            $remito = Remito::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => auth()->user()?->sucursal_id,
                'cliente_id' => $presupuesto->cliente_id,
                'presupuesto_id' => $presupuesto->id,
                'venta_id' => $presupuesto->venta_id,
                'user_id' => auth()->id() ?? $presupuesto->user_id,
                'numero' => $numero,
                'estado' => 'emitido',
                'fecha' => now()->toDateString(),
                'observaciones' => $presupuesto->observaciones,
            ]);

            foreach ($presupuesto->items as $item) {
                RemitoItem::create([
                    'remito_id' => $remito->id,
                    'producto_id' => $item->producto_id,
                    'descripcion' => $item->descripcion,
                    'cantidad' => $item->cantidad,
                ]);
            }

            return $remito->load('items');
        });
    }

    public function marcarEntregado(Remito $remito): Remito
    {
        if ($remito->estado === 'anulado') {
            throw new InvalidArgumentException('El remito está anulado.');
        }

        $remito->update(['estado' => 'entregado']);

        return $remito->fresh('items');
    }
}
