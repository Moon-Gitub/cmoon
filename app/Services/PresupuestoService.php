<?php

namespace App\Services;

use App\Models\Presupuesto;
use App\Models\PresupuestoItem;
use App\Models\Producto;
use Illuminate\Support\Facades\DB;

class PresupuestoService
{
    /**
     * @param  array<int, array{producto_id?: int|null, descripcion?: string, cantidad: float, precio_unitario: float}>  $items
     */
    public function crear(
        int $empresaId,
        int $userId,
        array $items,
        ?int $clienteId = null,
        ?string $observaciones = null,
        ?string $validoHasta = null,
        string $estado = 'pendiente',
        string $origen = 'web',
        ?string $uuid = null,
    ): Presupuesto {
        return DB::transaction(function () use ($empresaId, $userId, $items, $clienteId, $observaciones, $validoHasta, $estado, $origen, $uuid) {
            $numero = (int) Presupuesto::where('empresa_id', $empresaId)->lockForUpdate()->max('numero') + 1;

            $total = 0.0;
            $lineas = [];

            foreach ($items as $item) {
                $producto = isset($item['producto_id']) ? Producto::find($item['producto_id']) : null;
                $totalItem = round((float) $item['cantidad'] * (float) $item['precio_unitario'], 2);
                $total += $totalItem;
                $lineas[] = [
                    'producto_id' => $producto?->id,
                    'descripcion' => $item['descripcion'] ?? $producto?->nombre ?? 'Ítem',
                    'cantidad' => $item['cantidad'],
                    'precio_unitario' => $item['precio_unitario'],
                    'total' => $totalItem,
                ];
            }

            $presupuesto = Presupuesto::create([
                'uuid' => $uuid,
                'empresa_id' => $empresaId,
                'cliente_id' => $clienteId,
                'user_id' => $userId,
                'numero' => $numero,
                'estado' => $estado,
                'origen' => $origen,
                'total' => round($total, 2),
                'valido_hasta' => $validoHasta,
                'observaciones' => $observaciones,
                'fecha' => now()->toDateString(),
            ]);

            foreach ($lineas as $linea) {
                PresupuestoItem::create([
                    'presupuesto_id' => $presupuesto->id,
                    ...$linea,
                ]);
            }

            return $presupuesto->load('items');
        });
    }

    public function aprobar(Presupuesto $presupuesto): Presupuesto
    {
        abort_unless($presupuesto->estado === 'pendiente_aprobacion', 422, 'Solo se pueden aprobar pedidos pendientes de revisión.');

        $presupuesto->update(['estado' => 'aprobado']);

        return $presupuesto->fresh();
    }

    public function rechazar(Presupuesto $presupuesto, ?string $motivo = null): Presupuesto
    {
        abort_unless($presupuesto->estado === 'pendiente_aprobacion', 422, 'Solo se pueden rechazar pedidos pendientes de revisión.');

        $presupuesto->update([
            'estado' => 'rechazado',
            'observaciones' => trim(($presupuesto->observaciones ? $presupuesto->observaciones."\n" : '').($motivo ? "Rechazado: {$motivo}" : 'Rechazado desde el panel.')),
        ]);

        return $presupuesto->fresh();
    }
}
