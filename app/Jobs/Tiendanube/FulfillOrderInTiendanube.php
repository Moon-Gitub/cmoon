<?php

namespace App\Jobs\Tiendanube;

use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\Venta;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class FulfillOrderInTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public Venta $venta,
        public ?string $trackingNumber = null,
        public ?string $trackingUrl = null,
        public ?int $shippingCarrierId = null,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo) {
            return;
        }

        if (! $this->venta->tn_order_id) {
            TiendanubeLog::registrar(
                $this->integracion,
                'fulfillment',
                'push',
                'order',
                $this->venta->id,
                status: 'error',
                mensaje: 'La venta no tiene orden de Tiendanube asociada',
            );

            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);

        try {
            // Agregar tracking si existe
            if ($this->trackingNumber) {
                $service->addTrackingToOrder(
                    $this->venta->tn_order_id,
                    $this->trackingNumber,
                    $this->trackingUrl,
                    $this->shippingCarrierId,
                );
            }

            // Marcar como despachado
            $service->fulfillOrder($this->venta->tn_order_id);

            // Actualizar estado local
            $this->venta->update([
                'estado_envio' => 'despachado',
                'tracking_number' => $this->trackingNumber,
                'despachada_at' => now(),
            ]);

        } catch (\Throwable $e) {
            TiendanubeLog::registrar(
                $this->integracion,
                'fulfillment',
                'push',
                'order',
                $this->venta->id,
                status: 'error',
                mensaje: "Error al despachar: {$e->getMessage()}",
            );

            throw $e;
        }
    }
}
