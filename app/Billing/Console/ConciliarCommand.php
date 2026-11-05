<?php

namespace App\Billing\Console;

use App\Billing\Services\ReconciliationService;
use Illuminate\Console\Command;

class ConciliarCommand extends Command
{
    protected $signature = 'billing:conciliar {--dias=3}';

    protected $description = 'Concilia Mercado Pago contra el libro mayor.';

    public function handle(ReconciliationService $svc): int
    {
        $r = $svc->conciliar((int) $this->option('dias'));
        $this->line("Conciliación #{$r->id}: ".strtoupper($r->estado)." · revisados {$r->revisados} · acreditados {$r->acreditados} · alertas ".count($r->alertas ?? []));
        foreach ($r->alertas ?? [] as $a) {
            $this->warn(json_encode($a, JSON_UNESCAPED_UNICODE));
        }

        return $r->estado === 'error' ? self::FAILURE : self::SUCCESS;
    }
}
