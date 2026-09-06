<?php

namespace App\Services;

use App\Models\Compra;
use App\Models\CompraItem;
use App\Models\OrdenCompra;
use App\Models\OrdenCompraItem;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class OrdenCompraService
{
    public function __construct(private StockService $stockService) {}

    /**
     * @param  array{
     *   empresa_id?: int,
     *   sucursal_id: int,
     *   proveedor_id: int,
     *   user_id?: int,
     *   fecha?: string,
     *   observaciones?: string|null,
     *   estado?: string,
     *   items: list<array{producto_id?: int|null, descripcion?: string, cantidad: float, precio_unitario?: float, costo_unitario?: float}>
     * }  $datos
     */
    public function crear(array $datos): OrdenCompra
    {
        return DB::transaction(function () use ($datos) {
            $empresaId = (int) ($datos['empresa_id'] ?? auth()->user()->empresa_id);
            $userId = (int) ($datos['user_id'] ?? auth()->id());
            $numero = (int) OrdenCompra::withoutGlobalScopes()
                ->where('empresa_id', $empresaId)
                ->lockForUpdate()
                ->max('numero') + 1;

            $total = 0.0;
            $lineas = [];

            foreach ($datos['items'] as $item) {
                $producto = isset($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $cantidad = (float) $item['cantidad'];
                $precio = (float) ($item['precio_unitario'] ?? $item['costo_unitario'] ?? 0);
                $totalItem = round($cantidad * $precio, 2);
                $total += $totalItem;
                $lineas[] = [
                    'producto_id' => $producto?->id,
                    'descripcion' => $item['descripcion'] ?? $producto?->nombre ?? 'Ítem',
                    'cantidad' => $cantidad,
                    'cantidad_recibida' => 0,
                    'precio_unitario' => $precio,
                    'total' => $totalItem,
                ];
            }

            $orden = OrdenCompra::create([
                'empresa_id' => $empresaId,
                'sucursal_id' => $datos['sucursal_id'],
                'proveedor_id' => $datos['proveedor_id'],
                'user_id' => $userId,
                'numero' => $numero,
                'estado' => $datos['estado'] ?? 'borrador',
                'fecha' => $datos['fecha'] ?? now()->toDateString(),
                'observaciones' => $datos['observaciones'] ?? null,
                'total' => round($total, 2),
            ]);

            foreach ($lineas as $linea) {
                OrdenCompraItem::create([
                    'orden_compra_id' => $orden->id,
                    ...$linea,
                ]);
            }

            return $orden->load('items');
        });
    }

    /**
     * @param  list<array{id?: int, orden_compra_item_id?: int, cantidad_recibir: float}>  $items
     */
    public function recibirParcial(OrdenCompra $orden, array $items): OrdenCompra
    {
        if (in_array($orden->estado, ['anulada', 'recibida'], true)) {
            throw new InvalidArgumentException('La orden no admite más recepciones.');
        }

        $stockService = $this->stockService;

        return DB::transaction(function () use ($orden, $items, $stockService) {
            $orden->load('items.producto');
            $porId = collect($items)->keyBy(fn ($i) => (int) ($i['id'] ?? $i['orden_compra_item_id'] ?? 0));

            $recibidosAhora = [];
            foreach ($orden->items as $linea) {
                $pedido = $porId->get($linea->id);
                if (! $pedido) {
                    continue;
                }

                $qty = (float) ($pedido['cantidad_recibir'] ?? 0);
                if ($qty <= 0) {
                    continue;
                }

                $pendiente = (float) $linea->cantidad - (float) $linea->cantidad_recibida;
                $qty = min($qty, max(0, $pendiente));
                if ($qty <= 0) {
                    continue;
                }

                $linea->cantidad_recibida = round((float) $linea->cantidad_recibida + $qty, 3);
                $linea->save();
                $recibidosAhora[] = ['linea' => $linea, 'cantidad' => $qty];
            }

            if ($recibidosAhora === []) {
                throw new InvalidArgumentException('No hay cantidades para recibir.');
            }

            $totalCompra = 0.0;
            $compraItems = [];
            foreach ($recibidosAhora as $row) {
                /** @var OrdenCompraItem $linea */
                $linea = $row['linea'];
                $cantidad = (float) $row['cantidad'];
                $precio = (float) $linea->precio_unitario;
                $totalItem = round($cantidad * $precio, 2);
                $totalCompra += $totalItem;
                $compraItems[] = [
                    'producto' => $linea->producto,
                    'producto_id' => $linea->producto_id,
                    'descripcion' => $linea->descripcion,
                    'cantidad' => $cantidad,
                    'costo_unitario' => $precio,
                    'total' => $totalItem,
                ];
            }

            $compra = Compra::create([
                'empresa_id' => $orden->empresa_id,
                'sucursal_id' => $orden->sucursal_id,
                'proveedor_id' => $orden->proveedor_id,
                'user_id' => auth()->id() ?? $orden->user_id,
                'factura_numero' => null,
                'condicion' => 'contado',
                'total' => round($totalCompra, 2),
                'estado' => 'completada',
                'observaciones' => 'Recepción OC #'.$orden->numero,
                'fecha' => now()->toDateString(),
            ]);

            foreach ($compraItems as $item) {
                CompraItem::create([
                    'compra_id' => $compra->id,
                    'producto_id' => $item['producto_id'],
                    'descripcion' => $item['descripcion'],
                    'cantidad' => $item['cantidad'],
                    'costo_unitario' => $item['costo_unitario'],
                    'total' => $item['total'],
                ]);

                if ($item['producto']) {
                    $stockService->mover(
                        $item['producto'],
                        (int) $orden->sucursal_id,
                        (float) $item['cantidad'],
                        'compra',
                        "OC #{$orden->numero} / Compra #{$compra->id}",
                        $compra,
                        auth()->id() ?? $orden->user_id,
                    );
                }
            }

            $orden->refresh()->load('items');
            $completo = $orden->items->every(
                fn (OrdenCompraItem $i) => (float) $i->cantidad_recibida + 0.0001 >= (float) $i->cantidad
            );
            $orden->update(['estado' => $completo ? 'recibida' : 'parcial']);

            return $orden->fresh('items');
        });
    }
}
