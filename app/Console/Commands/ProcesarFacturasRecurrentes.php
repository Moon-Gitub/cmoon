<?php

namespace App\Console\Commands;

use App\Services\FacturaRecurrenteService;
use Illuminate\Console\Command;

class ProcesarFacturasRecurrentes extends Command
{
    protected $signature = 'facturas:recurrentes';

    protected $description = 'Envía recordatorios de facturas recurrentes vencidas y avanza el próximo vencimiento';

    public function handle(FacturaRecurrenteService $service): int
    {
        $procesadas = $service->procesarVencidas();

        $this->info("Facturas recurrentes procesadas: {$procesadas}");

        return self::SUCCESS;
    }
}
