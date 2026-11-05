<?php

namespace App\Billing\Console;

use App\Billing\Services\CustomerStatusService;
use App\Billing\Services\DunningService;
use Carbon\Carbon;
use Illuminate\Console\Command;

class AvisosCommand extends Command
{
    protected $signature = 'billing:avisos {--fecha= : YYYY-MM-DD para simular un día}';

    protected $description = 'Envía los avisos de cobranza del día (idempotente).';

    public function handle(DunningService $svc): int
    {
        $hoy = $this->option('fecha')
            ? Carbon::parse($this->option('fecha'), config('billing.timezone'))
            : CustomerStatusService::hoy();

        $r = $svc->ejecutar($hoy);
        $this->info(sprintf('Aviso %s: %d enviados, %d omitidos (ya enviados), %d fallidos.',
            $r['tipo'] ?? '(ninguno hoy o avisos desactivados)', $r['enviados'], $r['omitidos'], $r['fallidos']));

        return self::SUCCESS;
    }
}
