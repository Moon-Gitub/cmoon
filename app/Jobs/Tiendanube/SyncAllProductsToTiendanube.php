<?php

namespace App\Jobs\Tiendanube;

use App\Models\Producto;
use App\Models\TiendanubeIntegracion;
use App\Models\TiendanubeLog;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class SyncAllProductsToTiendanube implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public function __construct(
        public TiendanubeIntegracion $integracion,
    ) {}

    public function handle(): void
    {
        if (! $this->integracion->activo || ! $this->integracion->sync_products) {
            return;
        }

        $productos = Producto::where('empresa_id', $this->integracion->empresa_id)
            ->where('activo', true)
            ->get();

        $total = $productos->count();
        $synced = 0;
        $errors = 0;

        TiendanubeLog::registrar(
            $this->integracion,
            'product_sync',
            'push',
            mensaje: "Iniciando sincronización de {$total} productos",
        );

        foreach ($productos as $producto) {
            try {
                SyncProductToTiendanube::dispatchSync($this->integracion, $producto);
                $synced++;

                // Rate limiting: esperar entre requests
                usleep(500000); // 0.5 segundos entre productos
            } catch (\Throwable $e) {
                $errors++;
                report($e);
            }
        }

        $this->integracion->update(['last_product_sync_at' => now()]);

        TiendanubeLog::registrar(
            $this->integracion,
            'product_sync',
            'push',
            mensaje: "Sincronización completada: {$synced}/{$total} productos, {$errors} errores",
        );
    }
}
