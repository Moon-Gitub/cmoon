<?php

namespace App\Services\Moon;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * Consulta el estado del abono en el hub Moon Cobros. Fail-open: si el hub no
 * responde se usa el último estado bueno (hasta 72 h) y, si no hay, se permite vender.
 */
class MoonBillingClient
{
    private const CLAVE = 'moon_billing:estado';

    private const CLAVE_RESPALDO = 'moon_billing:ultimo_bueno';

    public function configurado(): bool
    {
        return config('moon.billing.url') !== '' && (string) config('moon.billing.key') !== '';
    }

    /** @return array{nivel: string, bloquear: bool, saldo: float, mensaje: ?string, pay_url: ?string, fuente: string}|null */
    public function estado(): ?array
    {
        if (! $this->configurado()) {
            return null;
        }

        return Cache::remember(self::CLAVE, now()->addMinutes((int) config('moon.billing.cache_min', 10)), function () {
            try {
                $r = Http::withToken((string) config('moon.billing.key'))
                    ->acceptJson()->timeout(5)->connectTimeout(3)
                    ->get(config('moon.billing.url').'/api/billing/v1/estado');

                if ($r->successful() && isset($r->json()['nivel'])) {
                    $estado = $this->normalizar($r->json(), 'hub');
                    Cache::put(self::CLAVE_RESPALDO, $estado, now()->addHours((int) config('moon.billing.fallback_horas', 72)));

                    return $estado;
                }
                Log::warning('MoonBilling: respuesta inesperada del hub', ['status' => $r->status()]);
            } catch (Throwable $e) {
                Log::warning('MoonBilling: hub inaccesible', ['error' => $e->getMessage()]);
            }

            $respaldo = Cache::get(self::CLAVE_RESPALDO);

            return $respaldo ? ['fuente' => 'respaldo'] + $respaldo : null;
        });
    }

    public function olvidar(): void
    {
        Cache::forget(self::CLAVE);
    }

    private function normalizar(array $d, string $fuente): array
    {
        return [
            'nivel' => (string) $d['nivel'],
            'bloquear' => (bool) ($d['bloquear'] ?? false) && (bool) config('moon.billing.enforce'),
            'saldo' => (float) ($d['saldo'] ?? 0),
            'mensaje' => $d['mensaje'] ?? null,
            'pay_url' => $d['pay_url'] ?? null,
            'fuente' => $fuente,
        ];
    }
}
