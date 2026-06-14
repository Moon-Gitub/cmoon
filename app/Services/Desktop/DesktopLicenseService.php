<?php

namespace App\Services\Desktop;

use App\Models\DesktopInstallation;
use App\Services\Moon\MoonCobroService;
use Illuminate\Support\Str;

/**
 * Emite y verifica tokens de licencia firmados para la app de escritorio.
 * El cliente valida offline con el device_token (HMAC).
 */
class DesktopLicenseService
{
    public function __construct(private MoonCobroService $moonCobro) {}

    public function emitir(DesktopInstallation $instalacion, ?string $deviceToken = null): array
    {
        $eval = $this->moonCobro->evaluarLicencia($instalacion->moon_client_id);
        $graceDays = (int) config('moon.offline_grace_days', 7);

        $validUntil = $eval['can_sell']
            ? now()->addDays($graceDays)->toIso8601String()
            : now()->toIso8601String();

        $payload = [
            'device_id' => $instalacion->device_id,
            'moon_client_id' => $instalacion->moon_client_id,
            'empresa_id' => $instalacion->empresa_id,
            'can_sell' => $eval['can_sell'],
            'blocked' => $eval['blocked'],
            'level' => $eval['level'],
            'message' => $eval['message'],
            'saldo' => $eval['saldo'],
            'valid_until' => $validUntil,
            'issued_at' => now()->toIso8601String(),
        ];

        return [
            'license' => $this->firmar($payload, $deviceToken),
            'status' => $eval,
            'offline_grace_days' => $graceDays,
        ];
    }

    /** @param  array<string, mixed>  $payload */
    public function firmar(array $payload, ?string $deviceToken): string
    {
        if (! $deviceToken) {
            throw new \InvalidArgumentException('Se requiere device_token para firmar la licencia.');
        }

        $json = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
        $body = base64_encode($json);
        $key = hash('sha256', $deviceToken);
        $sig = hash_hmac('sha256', $body, $key);

        return $body.'.'.$sig;
    }

    public function verificar(string $license, string $deviceToken): ?array
    {
        $partes = explode('.', $license, 2);
        if (count($partes) !== 2) {
            return null;
        }

        [$body, $sig] = $partes;
        $key = hash('sha256', $deviceToken);
        $esperado = hash_hmac('sha256', $body, $key);

        if (! hash_equals($esperado, $sig)) {
            return null;
        }

        $payload = json_decode(base64_decode($body), true);

        return is_array($payload) ? $payload : null;
    }

    public function crearTokenDispositivo(): string
    {
        return Str::random(64);
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
