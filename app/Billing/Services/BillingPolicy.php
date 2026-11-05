<?php

namespace App\Billing\Services;

use App\Billing\Models\Customer;
use App\Billing\Support\Money;
use Carbon\CarbonInterface;

/**
 * Reglas de cobranza puras (sin I/O): nivel, bloqueo y recargo para una fecha.
 *
 * Niveles: al_dia · pendiente · vencido · por_suspender · moroso · suspendido
 */
class BillingPolicy
{
    public function recargoPorcentaje(int $dia): float
    {
        $pct = 0.0;
        foreach (config('billing.reglas.recargos', []) as $tramo) {
            if ($dia >= (int) $tramo['desde_dia']) {
                $pct = (float) $tramo['porcentaje'];
            }
        }

        return $pct;
    }

    /**
     * @return array{nivel: string, bloquear: bool, saldo: int, recargo: int, deuda_anterior: int,
     *               cuota_periodo: int, dias_para_suspension: ?int, mensaje: ?string}
     */
    public function evaluar(Customer $c, int $saldo, int $cuotaPeriodo, CarbonInterface $hoy, bool $recargoYaAplicado): array
    {
        $tol = (int) config('billing.reglas.tolerancia_centavos', 10000);
        $dia = (int) $hoy->day;
        $diaVto = (int) config('billing.reglas.dia_vencimiento', 10);
        $diaSusp = (int) config('billing.reglas.dia_suspension', 26);

        $base = [
            'saldo' => $saldo,
            'cuota_periodo' => $cuotaPeriodo,
            'recargo' => 0,
            'deuda_anterior' => 0,
            'dias_para_suspension' => null,
            'bloquear' => false,
        ];

        if ($saldo <= $tol) {
            return $base + ['nivel' => 'al_dia', 'mensaje' => null];
        }

        $deudaAnterior = max(0, $saldo - $cuotaPeriodo);
        $pct = $c->aplicar_recargos ? $this->recargoPorcentaje($dia) : 0.0;
        $recargo = ($recargoYaAplicado || $pct <= 0)
            ? 0
            : (int) round(min($saldo, $cuotaPeriodo) * $pct / 100);

        $conProrroga = $c->prorroga_hasta !== null && $c->prorroga_hasta->gte($hoy->copy()->startOfDay());
        $suspendible = $c->modo_cobro === 'bloqueo' && ! $conProrroga;
        $vencidoGrave = $deudaAnterior > $tol || $dia >= $diaSusp;

        $total = Money::formato($saldo + $recargo);
        $r = array_merge($base, ['recargo' => $recargo, 'deuda_anterior' => $deudaAnterior]);

        if ($vencidoGrave) {
            return array_merge($r, [
                'nivel' => $suspendible ? 'suspendido' : 'moroso',
                'bloquear' => $suspendible,
                'mensaje' => $suspendible
                    ? "Sistema suspendido por falta de pago. Total a regularizar: {$total}."
                    : "Tenés abonos vencidos por {$total}. Regularizá para evitar la suspensión.",
            ]);
        }

        $diasParaSusp = $diaSusp - $dia;
        $tramo2 = (int) (config('billing.reglas.recargos.1.desde_dia') ?? 21);

        if ($dia >= $tramo2 && $suspendible) {
            return array_merge($r, ['dias_para_suspension' => $diasParaSusp]) + [
                'nivel' => 'por_suspender',
                'mensaje' => "Abono vencido: {$total}. El sistema se suspende en {$diasParaSusp} día(s).",
            ];
        }

        if ($dia > $diaVto) {
            return $r + [
                'nivel' => 'vencido',
                'mensaje' => "Abono vencido. Total a pagar: {$total}.",
            ];
        }

        return $r + [
            'nivel' => 'pendiente',
            'mensaje' => "Abono del mes: {$total}. Vence el día {$diaVto}.",
        ];
    }
}
