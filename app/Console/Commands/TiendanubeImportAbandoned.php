<?php

namespace App\Console\Commands;

use App\Jobs\Tiendanube\ImportAbandonedCarts;
use App\Models\TiendanubeIntegracion;
use Illuminate\Console\Command;

class TiendanubeImportAbandoned extends Command
{
    protected $signature = 'tiendanube:import-abandoned
                            {--empresa= : ID de empresa específica}
                            {--days=7 : Días hacia atrás para importar}
                            {--sync : Ejecutar sincrónicamente}';

    protected $description = 'Importar carritos abandonados de Tiendanube';

    public function handle(): int
    {
        $query = TiendanubeIntegracion::where('activo', true)
            ->where('import_abandoned', true);

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay integraciones con importación de carritos abandonados activa.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $sync = $this->option('sync');

        foreach ($integraciones as $integracion) {
            $this->info("Importando carritos abandonados: {$integracion->store_name}");

            if ($sync) {
                ImportAbandonedCarts::dispatchSync($integracion, $days);
            } else {
                ImportAbandonedCarts::dispatch($integracion, $days);
            }
        }

        $this->info('Importación iniciada.');

        return self::SUCCESS;
    }
}
