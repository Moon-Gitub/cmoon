<?php

namespace App\Services\Moon;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

/**
 * Consulta el sistema de cobros Moon (BD externa) — misma lógica que el POS legacy.
 */
class MoonCobroService
{
    public function habilitado(): bool
    {
        return (bool) config('moon.cobro.enabled')
            && config('moon.cobro.database')
            && config('moon.cobro.username');
    }

    public function cliente(int $moonClientId): ?object
    {
        if (! $this->habilitado()) {
            return null;
        }

        try {
            return DB::connection('moon_cobro')
                ->table('clientes')
                ->where('id', $moonClientId)
                ->first();
        } catch (\Throwable $e) {
            Log::warning('MoonCobro: no se pudo consultar cliente', ['id' => $moonClientId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    public function saldoCuenta(int $moonClientId): ?object
    {
        if (! $this->habilitado()) {
            return null;
        }

        try {
            return DB::connection('moon_cobro')
                ->selectOne(
                    'SELECT SUM(IF (cc.tipo = 0, cc.importe, 0)) AS ventas,
                            SUM(IF (cc.tipo = 1, cc.importe, 0)) AS pagos,
                            (SUM(IF (cc.tipo = 0, cc.importe, 0)) - SUM(IF (cc.tipo = 1, cc.importe, 0))) AS saldo
                     FROM clientes_cuenta_corriente cc WHERE cc.id_cliente = ?',
                    [$moonClientId]
                );
        } catch (\Throwable $e) {
            Log::warning('MoonCobro: error saldo', ['id' => $moonClientId, 'error' => $e->getMessage()]);

            return null;
        }
    }

    /**
     * Estado de licencia según saldo, bloqueo manual y día del mes (reglas legacy).
     *
     * @return array{can_sell: bool, blocked: bool, level: string, message: ?string, saldo: float, mensual: float}
     */
    public function evaluarLicencia(int $moonClientId): array
    {
        $cliente = $this->cliente($moonClientId);
        $cta = $this->saldoCuenta($moonClientId);

        // Sin conexión a cobros: permitir con gracia (el token offline cubre el período)
        if (! $cliente || ! $cta) {
            return [
                'can_sell' => true,
                'blocked' => false,
                'level' => 'unknown',
                'message' => null,
                'saldo' => 0.0,
                'mensual' => 0.0,
            ];
        }

        $saldo = (float) ($cta->saldo ?? 0);
        $mensual = (float) ($cliente->mensual ?? 0);
        $bloqueoManual = (int) ($cliente->estado_bloqueo ?? 0) === 1;
        $dia = (int) date('j');
        $diaBloqueo = (int) config('moon.bloqueo_dia_mes', 26);

        if ($saldo <= 0) {
            $this->desbloquear($moonClientId);

            return [
                'can_sell' => true,
                'blocked' => false,
                'level' => 'ok',
                'message' => null,
                'saldo' => $saldo,
                'mensual' => $mensual,
            ];
        }

        if ($bloqueoManual || $dia >= $diaBloqueo) {
            return [
                'can_sell' => false,
                'blocked' => true,
                'level' => 'blocked',
                'message' => 'Sistema suspendido por falta de pago. Regularice su situación en Moon.',
                'saldo' => $saldo,
                'mensual' => $mensual,
            ];
        }

        if ($dia > 20) {
            return [
                'can_sell' => true,
                'blocked' => false,
                'level' => 'warning',
                'message' => "Abono pendiente: \${$saldo}. El sistema se suspenderá el día {$diaBloqueo}.",
                'saldo' => $saldo,
                'mensual' => $mensual,
            ];
        }

        if ($dia > 9) {
            return [
                'can_sell' => true,
                'blocked' => false,
                'level' => 'reminder',
                'message' => "Recuerde abonar el servicio Moon. Saldo: \${$saldo}.",
                'saldo' => $saldo,
                'mensual' => $mensual,
            ];
        }

        return [
            'can_sell' => true,
            'blocked' => false,
            'level' => 'ok',
            'message' => null,
            'saldo' => $saldo,
            'mensual' => $mensual,
        ];
    }

    private function desbloquear(int $moonClientId): void
    {
        try {
            DB::connection('moon_cobro')
                ->table('clientes')
                ->where('id', $moonClientId)
                ->update(['estado_bloqueo' => 0]);
        } catch (\Throwable) {
            // No bloquear operación por fallo de escritura en cobros
        }
    }
}
