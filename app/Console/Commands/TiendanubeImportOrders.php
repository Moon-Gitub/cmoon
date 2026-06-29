<?php

namespace App\Console\Commands;

use App\Jobs\Tiendanube\ImportRecentOrders;
use App\Models\TiendanubeIntegracion;
use Illuminate\Console\Command;

class TiendanubeImportOrders extends Command
{
    protected $signature = 'tiendanube:import-orders
                            {--empresa= : ID de empresa específica}
                            {--days=7 : Días hacia atrás para importar}
                            {--sync : Ejecutar sincrónicamente}';

    protected $description = 'Importar órdenes recientes de Tiendanube';

    public function handle(): int
    {
        $query = TiendanubeIntegracion::where('activo', true)
            ->where('sync_orders', true);

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay integraciones con importación de órdenes activa.');

            return self::SUCCESS;
        }

        $days = (int) $this->option('days');
        $sync = $this->option('sync');

        foreach ($integraciones as $integracion) {
            $this->info("Importando órdenes: {$integracion->store_name}");

            if ($sync) {
                ImportRecentOrders::dispatchSync($integracion, $days);
            } else {
                ImportRecentOrders::dispatch($integracion, $days);
            }
        }

        $this->info('Importación iniciada.');

        return self::SUCCESS;
    }
}
