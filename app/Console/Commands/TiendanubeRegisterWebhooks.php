<?php

namespace App\Console\Commands;

use App\Models\TiendanubeIntegracion;
use App\Services\TiendanubeService;
use Illuminate\Console\Command;

class TiendanubeRegisterWebhooks extends Command
{
    protected $signature = 'tiendanube:register-webhooks
                            {--empresa= : ID de empresa específica}
                            {--force : Eliminar webhooks existentes y re-registrar}';

    protected $description = 'Registrar webhooks en Tiendanube';

    public function handle(): int
    {
        $query = TiendanubeIntegracion::where('activo', true);

        if ($empresaId = $this->option('empresa')) {
            $query->where('empresa_id', $empresaId);
        }

        $integraciones = $query->get();

        if ($integraciones->isEmpty()) {
            $this->warn('No hay integraciones activas.');

            return self::SUCCESS;
        }

        $tiendanube = TiendanubeService::make();
        $webhookUrl = route('tiendanube.webhook');
        $force = $this->option('force');

        $this->info("URL de webhook: {$webhookUrl}");
        $this->newLine();

        foreach ($integraciones as $integracion) {
            $this->info("Procesando: {$integracion->store_name}");

            $service = $tiendanube->forIntegracion($integracion);

            try {
                // Eliminar webhooks existentes si --force
                if ($force) {
                    $this->line('  Eliminando webhooks existentes...');
                    $existing = $service->getWebhooks();

                    foreach ($existing as $webhook) {
                        $service->deleteWebhook($webhook['id']);
                        $this->line("    Eliminado: {$webhook['event']}");
                    }
                }

                // Registrar nuevos webhooks
                $this->line('  Registrando webhooks...');
                $registered = $service->registerAllWebhooks($webhookUrl);

                foreach ($registered as $webhook) {
                    $this->info("    ✓ {$webhook['event']}");
                }

                // Actualizar secret
                $integracion->generateWebhookSecret();

            } catch (\Throwable $e) {
                $this->error("  Error: {$e->getMessage()}");
            }
        }

        return self::SUCCESS;
    }
}
