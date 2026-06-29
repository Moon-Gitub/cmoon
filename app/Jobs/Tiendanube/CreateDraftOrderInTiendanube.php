<?php

namespace App\Jobs\Tiendanube;

use App\Models\Cliente;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\TiendanubeProductMap;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class CreateDraftOrderInTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public ?Cliente $cliente,
        public array $items, // [['producto_id' => 1, 'cantidad' => 2], ...]
        public ?string $note = null,
    ) {}

    public function handle(TiendanubeService $tiendanube): ?array
    {
        if (! $this->integracion->activo) {
            return null;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        // Construir líneas de productos
        $lineItems = [];

        foreach ($this->items as $item) {
            $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
                ->where('producto_id', $item['producto_id'])
                ->first();

            if (! $map) {
                TiendanubeLog::registrar(
                    $this->integracion,
                    'draft_order',
                    'push',
                    'product',
                    $item['producto_id'],
                    status: 'error',
                    mensaje: "Producto #{$item['producto_id']} no mapeado en Tiendanube",
                );

                continue;
            }

            $lineItems[] = [
                'variant_id' => $map->tn_variant_id,
                'quantity' => $item['cantidad'],
            ];
        }

        if (empty($lineItems)) {
            TiendanubeLog::registrar(
                $this->integracion,
                'draft_order',
                'push',
                status: 'error',
                mensaje: 'No hay productos válidos para crear orden borrador',
            );

            return null;
        }

        // Construir datos del cliente si existe
        $customerData = null;

        if ($this->cliente) {
            $customerData = [
                'name' => $this->cliente->nombre,
                'email' => $this->cliente->email,
            ];

            if ($this->cliente->telefono) {
                $customerData['phone'] = $this->cliente->telefono;
            }
        }

        try {
            $data = [
                'line_items' => $lineItems,
            ];

            if ($customerData) {
                $data['customer'] = $customerData;
            }

            if ($this->note) {
                $data['note'] = $this->note;
            }

            $result = $service->createDraftOrder($data);

            TiendanubeLog::registrar(
                $this->integracion,
                'draft_order',
                'push',
                response: $result,
                mensaje: 'Orden borrador creada: #'.($result['id'] ?? 'N/A'),
            );

            return $result;
        } catch (\Throwable $e) {
            TiendanubeLog::registrar(
                $this->integracion,
                'draft_order',
                'push',
                status: 'error',
                mensaje: "Error al crear orden borrador: {$e->getMessage()}",
            );

            throw $e;
        }
    }
}
