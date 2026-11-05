<?php

namespace App\Billing\Console;

use App\Billing\Services\CustomerStatusService;
use App\Billing\Services\InvoiceGenerator;
use Carbon\Carbon;
use Illuminate\Console\Command;

class GenerarCuotasCommand extends Command
{
    protected $signature = 'billing:generar-cuotas {--periodo= : YYYY-MM (por defecto, el mes actual)} {--forzar : generar aunque BILLING_GENERAR_CUOTAS=false}';

    protected $description = 'Genera las cuotas del mes (idempotente).';

    public function handle(InvoiceGenerator $gen): int
    {
        if (! config('billing.cuotas.generar') && ! $this->option('forzar')) {
            $this->warn('BILLING_GENERAR_CUOTAS=false: el legacy sigue generando las cuotas. Usá --forzar para probar.');

            return self::SUCCESS;
        }

        $mes = $this->option('periodo')
            ? Carbon::createFromFormat('Y-m-d', $this->option('periodo').'-01', config('billing.timezone'))
            : CustomerStatusService::hoy();

        $r = $gen->generar($mes);
        $this->info("Período {$mes->format('Y-m')}: {$r['creadas']} cuotas nuevas, {$r['existentes']} ya existían.");

        return self::SUCCESS;
    }
}
