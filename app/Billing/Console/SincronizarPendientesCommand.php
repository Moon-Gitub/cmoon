<?php

namespace App\Billing\Console;

use App\Billing\Services\ReconciliationService;
use Illuminate\Console\Command;

class SincronizarPendientesCommand extends Command
{
    protected $signature = 'billing:sincronizar-pendientes {--horas=72}';

    protected $description = 'Busca en Mercado Pago pagos de checkouts abiertos cuyo webhook no llegó.';

    public function handle(ReconciliationService $svc): int
    {
        $n = $svc->sincronizarPendientes((int) $this->option('horas'));
        $this->info("Pagos acreditados por barrido: {$n}");

        return self::SUCCESS;
    }
}
