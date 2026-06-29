<?php

namespace App\Console\Commands;

use App\Jobs\Tiendanube\SyncPromotionalPrices;
use App\Models\TiendanubeIntegracion;
use Illuminate\Console\Command;

class TiendanubeSyncPrices extends Command
{
    protected $signature = 'tiendanube:sync-prices
                            {--empresa= : ID de empresa específica}
                            {--sync : Ejecutar sincrónicamente}';

    protected $description = 'Sincronizar precios promocionales con Tiendanube';

    public function handle(): int
    {
        $query = TiendanubeIntegracion::where('activo', true)
            ->where('sync_prices', true);

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay integraciones con sincronización de precios activa.');

            return self::SUCCESS;
        }

        $sync = $this->option('sync');

        foreach ($integraciones as $integracion) {
            $this->info("Sincronizando precios: {$integracion->store_name}");

            if ($sync) {
                SyncPromotionalPrices::dispatchSync($integracion);
            } else {
                SyncPromotionalPrices::dispatch($integracion);
            }
        }

        $this->info('Sincronización iniciada.');

        return self::SUCCESS;
    }
}
