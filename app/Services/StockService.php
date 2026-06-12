<?php

namespace App\Services;

use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Stock;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class StockService
{
    /**
     * Aplica un movimiento de stock de forma atómica (con lock de fila)
     * y deja registro en el historial.
     *
     * @param  float  $cantidad  Positiva entra, negativa sale
     */
    public function mover(
        Producto $producto,
        int $sucursalId,
        float $cantidad,
        string $tipo,
        ?string $observacion = null,
        ?Model $referencia = null,
        ?int $userId = null,
    ): Stock {
        return DB::transaction(function () use ($producto, $sucursalId, $cantidad, $tipo, $observacion, $referencia, $userId) {
            $stock = Stock::lockForUpdate()->firstOrCreate(
                ['producto_id' => $producto->id, 'sucursal_id' => $sucursalId],
                ['cantidad' => 0]
            );

            $stock->cantidad = (float) $stock->cantidad + $cantidad;
            $stock->save();

            MovimientoStock::create([
                'producto_id' => $producto->id,
                'sucursal_id' => $sucursalId,
                'user_id' => $userId ?? auth()->id(),
                'tipo' => $tipo,
                'cantidad' => $cantidad,
                'stock_resultante' => $stock->cantidad,
                'observacion' => $observacion,
                'referencia_type' => $referencia?->getMorphClass(),
                'referencia_id' => $referencia?->getKey(),
            ]);

            return $stock;
        });
    }

    /**
     * Fija el stock en un valor absoluto registrando el ajuste por diferencia.
     */
    public function ajustarA(
        Producto $producto,
        int $sucursalId,
        float $cantidadFinal,
        ?string $observacion = null,
    ): Stock {
        $actual = Stock::where('producto_id', $producto->id)
            ->where('sucursal_id', $sucursalId)
            ->value('cantidad') ?? 0;

        return $this->mover(
            $producto,
            $sucursalId,
            $cantidadFinal - (float) $actual,
            'ajuste',
            $observacion ?? 'Ajuste manual de stock',
        );
    }
}
