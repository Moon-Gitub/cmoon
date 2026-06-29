<?php

namespace App\Console\Commands;

use App\Jobs\Tiendanube\SyncLocationsStock;
use App\Models\TiendanubeIntegracion;
use Illuminate\Console\Command;

class TiendanubeSyncLocations extends Command
{
    protected $signature = 'tiendanube:sync-locations
                            {--empresa= : ID de empresa específica}
                            {--sync : Ejecutar sincrónicamente}';

    protected $description = 'Sincronizar stock por ubicación con Tiendanube';

    public function handle(): int
    {
        $query = TiendanubeIntegracion::where('activo', true)
            ->where('sync_stock', true);

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay integraciones con sincronización de stock activa.');

            return self::SUCCESS;
        }

        $sync = $this->option('sync');

        foreach ($integraciones as $integracion) {
            $this->info("Sincronizando stock por ubicación: {$integracion->store_name}");

            if ($sync) {
                SyncLocationsStock::dispatchSync($integracion);
            } else {
                SyncLocationsStock::dispatch($integracion);
            }
        }

        $this->info('Sincronización iniciada.');

        return self::SUCCESS;
    }
}
