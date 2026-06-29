<?php

namespace App\Console\Commands;

use App\Models\TiendanubeIntegracion;
use App\Services\TiendanubeService;
use Illuminate\Console\Command;

class TiendanubeTest extends Command
{
    protected $signature = 'tiendanube:test
                            {--empresa= : ID de empresa específica}';

    protected $description = 'Probar conexión con Tiendanube';

    public function handle(): int
    {
        // Verificar configuración
        if (blank(config('tiendanube.client_id'))) {
            $this->error('TIENDANUBE_CLIENT_ID no está configurado en .env');

            return self::FAILURE;
        }

        if (blank(config('tiendanube.client_secret'))) {
            $this->error('TIENDANUBE_CLIENT_SECRET no está configurado en .env');

            return self::FAILURE;
        }

        $this->info('Configuración OK');
        $this->line('  Client ID: '.config('tiendanube.client_id'));
        $this->line('  User Agent: '.config('tiendanube.user_agent'));

        // Probar integraciones
        $query = TiendanubeIntegracion::query();

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay tiendas conectadas.');

            return self::SUCCESS;
        }

        $this->newLine();
        $this->info('Probando conexiones:');

        $tiendanube = TiendanubeService::make();

        foreach ($integraciones as $integracion) {
            $this->line("  {$integracion->store_name} (ID: {$integracion->store_id})");

            try {
                $store = $tiendanube->forIntegracion($integracion)->getStore();

                if ($store) {
                    $this->info("    ✓ Conexión exitosa");
                    $this->line("    Nombre: ".($store['name']['es'] ?? $store['name']['en'] ?? 'N/A'));
                    $this->line("    Plan: ".($store['plan_name'] ?? 'N/A'));
                } else {
                    $this->error("    ✗ No se pudo obtener info de la tienda");
                }
            } catch (\Throwable $e) {
                $this->error("    ✗ Error: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
