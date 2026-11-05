<?php

namespace App\Billing\Services;

use Illuminate\Http\Request;

/**
 * Firma de notificaciones de Mercado Pago (header x-signature: "ts=...,v1=...").
 * Manifest: id:{data.id en minúsculas};request-id:{x-request-id};ts:{ts};
 */
class WebhookSignature
{
    public function valida(Request $request, ?int $ahora = null): bool
    {
        $secret = (string) config('billing.mercadopago.webhook_secret');
        if ($secret === '') {
            return false;
        }

        $partes = [];
        foreach (explode(',', (string) $request->header('x-signature')) as $par) {
            [$k, $v] = array_pad(array_map('trim', explode('=', $par, 2)), 2, null);
            if ($k !== null && $v !== null) {
                $partes[$k] = $v;
            }
        }

        $ts = $partes['ts'] ?? null;
        $v1 = $partes['v1'] ?? null;
        if (! $ts || ! $v1 || ! ctype_digit($ts)) {
            return false;
        }

        $tolerancia = (int) config('billing.mercadopago.webhook_tolerancia_seg', 600);
        $tsSeg = strlen($ts) > 10 ? intdiv((int) $ts, 1000) : (int) $ts;
        if ($tolerancia > 0 && abs(($ahora ?? time()) - $tsSeg) > $tolerancia) {
            return false;
        }

        return hash_equals(self::firmar($secret, $this->dataId($request), (string) $request->header('x-request-id'), $ts), $v1);
    }

    public static function firmar(string $secret, string $dataId, string $requestId, string $ts): string
    {
        $manifest = [];
        if ($dataId !== '') {
            $manifest[] = 'id:'.strtolower($dataId);
        }
        if ($requestId !== '') {
            $manifest[] = 'request-id:'.$requestId;
        }
        $manifest[] = 'ts:'.$ts;

        return hash_hmac('sha256', implode(';', $manifest).';', $secret);
    }

    private function dataId(Request $request): string
    {
        return (string) ($request->query('data_id') ?? $request->query('data.id') ?? '');
    }
}
