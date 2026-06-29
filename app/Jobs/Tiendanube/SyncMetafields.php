<?php

namespace App\Jobs\Tiendanube;

use App\Models\Producto;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use App\Models\TiendanubeProductMap;
use App\Services\TiendanubeService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncMetafields implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 60;

    public function __construct(
        public TiendanubeIntegracion $integracion,
        public Producto $producto,
    ) {}

    public function handle(TiendanubeService $tiendanube): void
    {
        if (! $this->integracion->activo) {
            return;
        }

        $map = TiendanubeProductMap::where('integracion_id', $this->integracion->id)
            ->where('producto_id', $this->producto->id)
            ->first();

        if (! $map) {
            return;
        }

        $service = $tiendanube->forIntegracion($this->integracion);
        $namespace = 'posmoon';

        // Definir metafields a sincronizar
        $metafields = [];

        // Garantía
        if ($this->producto->garantia_meses) {
            $metafields['garantia'] = [
                'value' => $this->producto->garantia_meses.' meses',
                'type' => 'string',
            ];
        }

        // SKU interno
        if ($this->producto->codigo_interno) {
            $metafields['sku_interno'] = [
                'value' => $this->producto->codigo_interno,
                'type' => 'string',
            ];
        }

        // Peso
        if ($this->producto->peso) {
            $metafields['peso'] = [
                'value' => (string) $this->producto->peso,
                'type' => 'string',
            ];
        }

        // Dimensiones
        if ($this->producto->alto && $this->producto->ancho && $this->producto->largo) {
            $metafields['dimensiones'] = [
                'value' => "{$this->producto->largo}x{$this->producto->ancho}x{$this->producto->alto} cm",
                'type' => 'string',
            ];
        }

        // Marca
        if ($this->producto->marca) {
            $metafields['marca'] = [
                'value' => $this->producto->marca,
                'type' => 'string',
            ];
        }

        // Origen
        if ($this->producto->origen) {
            $metafields['origen'] = [
                'value' => $this->producto->origen,
                'type' => 'string',
            ];
        }

        if (empty($metafields)) {
            return;
        }

        // Obtener metafields existentes
        $existingMetafields = $service->getProductMetafields($map->tn_product_id);
        $existingByKey = collect($existingMetafields)->where('namespace', $namespace)->keyBy('key');

        $synced = 0;

        foreach ($metafields as $key => $data) {
            try {
                if ($existingByKey->has($key)) {
                    // Actualizar
                    $existing = $existingByKey->get($key);
                    $service->updateProductMetafield(
                        $map->tn_product_id,
                        $existing['id'],
                        $data['value'],
                    );
                } else {
                    // Crear
                    $service->createProductMetafield(
                        $map->tn_product_id,
                        $namespace,
                        $key,
                        $data['value'],
                        $data['type'],
                    );
                }

                $synced++;
            } catch (\Throwable $e) {
                TiendanubeLog::registrar(
                    $this->integracion,
                    'metafield_sync',
                    'push',
                    'product',
                    $this->producto->id,
                    status: 'error',
                    mensaje: "Error sync metafield {$key}: {$e->getMessage()}",
                );
            }
        }

        if ($synced > 0) {
            TiendanubeLog::registrar(
                $this->integracion,
                'metafield_sync',
                'push',
                'product',
                $this->producto->id,
                mensaje: "Sincronizados {$synced} metafields para {$this->producto->nombre}",
            );
        }
    }
}
