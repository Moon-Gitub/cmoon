<?php

namespace App\Console\Commands;

use App\Jobs\Tiendanube\SyncAllProductsToTiendanube;
use App\Jobs\Tiendanube\SyncAllStockToTiendanube;
use App\Models\TiendanubeIntegracion;
use Illuminate\Console\Command;

class TiendanubeSync extends Command
{
    protected $signature = 'tiendanube:sync
                            {--empresa= : ID de empresa específica}
                            {--products : Solo sincronizar productos}
                            {--stock : Solo sincronizar stock}
                            {--sync : Ejecutar sincrónicamente (sin queue)}';

    protected $description = 'Sincronizar productos y stock con Tiendanube';

    public function handle(): int
    {
        $query = TiendanubeIntegracion::where('activo', true);

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay integraciones activas de Tiendanube.');

            return self::SUCCESS;
        }

        $onlyProducts = $this->option('products');
        $onlyStock = $this->option('stock');
        $sync = $this->option('sync');

        foreach ($integraciones as $integracion) {
            $this->info("Procesando: {$integracion->store_name} (Empresa #{$integracion->empresa_id})");

            // Sincronizar productos
            if (! $onlyStock && $integracion->sync_products) {
                $this->line('  → Sincronizando productos...');

                if ($sync) {
                    SyncAllProductsToTiendanube::dispatchSync($integracion);
                } else {
                    SyncAllProductsToTiendanube::dispatch($integracion);
                }
            }

            // Sincronizar stock
            if (! $onlyProducts && $integracion->sync_stock && $integracion->default_sucursal_id) {
                $this->line('  → Sincronizando stock...');

                if ($sync) {
                    SyncAllStockToTiendanube::dispatchSync($integracion);
                } else {
                    SyncAllStockToTiendanube::dispatch($integracion);
                }
            }
        }

        $this->info('Sincronización iniciada. Revisá los logs en el panel.');

        return self::SUCCESS;
    }
}
