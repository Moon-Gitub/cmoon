<?php

namespace App\Observers;

use App\Models\Producto;
use App\Models\ProductoAuditoria;

class ProductoObserver
{
    private const CAMPOS = ['nombre', 'precio_venta', 'precio_compra', 'codigo', 'activo'];

    public function updated(Producto $producto): void
    {
        foreach (self::CAMPOS as $campo) {
            if (! $producto->wasChanged($campo)) {
                continue;
            }

            ProductoAuditoria::create([
                'producto_id' => $producto->id,
                'user_id' => auth()->id(),
                'campo' => $campo,
                'valor_anterior' => (string) $producto->getOriginal($campo),
                'valor_nuevo' => (string) $producto->getAttribute($campo),
                'origen' => 'update',
                'created_at' => now(),
            ]);
        }

        // Stock se audita vía movimientos_stock; no duplicar aquí.
    }
}
