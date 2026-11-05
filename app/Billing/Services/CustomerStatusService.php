<?php

namespace App\Billing\Services;

use App\Billing\Models\Customer;
use App\Billing\Models\Invoice;
use Carbon\CarbonInterface;

class CustomerStatusService
{
    public function __construct(
        private LedgerService $ledger,
        private BillingPolicy $policy,
    ) {}

    public static function hoy(): CarbonInterface
    {
        return now(config('billing.timezone'));
    }

    public static function referenciaRecargo(Customer $c, string $periodo): string
    {
        return "recargo:{$c->id}:{$periodo}";
    }

    public function cuotaPeriodo(Customer $c, string $periodo): int
    {
        return (int) Invoice::where('customer_id', $c->id)->where('periodo', $periodo)->sum('importe_centavos');
    }

    public function estado(Customer $c, ?CarbonInterface $hoy = null, ?int $saldo = null): array
    {
        $hoy ??= self::hoy();
        $periodo = $hoy->format('Y-m');
        $saldo ??= $this->ledger->saldo($c);

        $eval = $this->policy->evaluar(
            $c,
            $saldo,
            $this->cuotaPeriodo($c, $periodo),
            $hoy,
            $this->ledger->existe(self::referenciaRecargo($c, $periodo)),
        );

        return $eval + [
            'periodo' => $periodo,
            'customer_id' => $c->id,
            'cliente' => $c->nombre,
            'modo_cobro' => $c->modo_cobro,
            'pay_url' => $c->payUrl(),
        ];
    }
}
