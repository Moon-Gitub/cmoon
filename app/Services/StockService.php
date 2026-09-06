<?php

namespace App\Services;

use App\Events\StockUpdated;
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
     * @param  array{deposito_id?: int|null, lote_id?: int|null}  $options  Opcional: si viene deposito_id/lote_id se puede ajustar ProductoLote (CRUD independiente por ahora).
     */
    public function mover(
        Producto $producto,
        int $sucursalId,
        float $cantidad,
        string $tipo,
        ?string $observacion = null,
        ?Model $referencia = null,
        ?int $userId = null,
        array $options = [],
    ): Stock {
        $stock = DB::transaction(function () use ($producto, $sucursalId, $cantidad, $tipo, $observacion, $referencia, $userId, $options) {
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

            // Hook liviano: actualizar cantidad de lote si se indica lote_id.
            if (! empty($options['lote_id']) && class_exists(\App\Models\ProductoLote::class)) {
                $lote = \App\Models\ProductoLote::lockForUpdate()->find($options['lote_id']);
                if ($lote && (int) $lote->producto_id === (int) $producto->id) {
                    if (! empty($options['deposito_id'])) {
                        $lote->deposito_id = (int) $options['deposito_id'];
                    }
                    $lote->cantidad = (float) $lote->cantidad + $cantidad;
                    $lote->save();
                }
            }

            return $stock;
        });

        // Disparar evento para integraciones (Tiendanube, etc.) - no debe fallar si hay error
        try {
            StockUpdated::dispatch($producto, $sucursalId, $stock->cantidad);
        } catch (\Throwable) {
            // Silenciar errores de integraciones para no afectar operación principal
        }

        return $stock;
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
