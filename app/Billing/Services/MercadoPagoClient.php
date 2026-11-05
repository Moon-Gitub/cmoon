<?php

namespace App\Billing\Services;

use Carbon\CarbonInterface;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class MercadoPagoClient
{
    public function configurado(): bool
    {
        return (string) config('billing.mercadopago.access_token') !== '';
    }

    /** null = Mercado Pago no conoce el pago (404). Excepción = no se pudo verificar. */
    public function payment(string $id): ?array
    {
        $r = $this->http()->get('/v1/payments/'.rawurlencode($id));

        if ($r->status() === 404) {
            return null;
        }
        if (! $r->successful() || ! isset($r->json()['id'])) {
            throw new RuntimeException("Mercado Pago respondió HTTP {$r->status()} al consultar el pago {$id}");
        }

        return $r->json();
    }

    public function merchantOrder(string $id): ?array
    {
        $r = $this->http()->get('/merchant_orders/'.rawurlencode($id));

        if ($r->status() === 404) {
            return null;
        }
        if (! $r->successful()) {
            throw new RuntimeException("Mercado Pago respondió HTTP {$r->status()} al consultar la orden {$id}");
        }

        return $r->json();
    }

    public function crearPreferencia(array $preferencia, string $idempotencyKey): array
    {
        $r = $this->http()
            ->withHeaders(['X-Idempotency-Key' => $idempotencyKey])
            ->post('/checkout/preferences', $preferencia);

        if (! $r->successful() || empty($r->json()['id'])) {
            throw new RuntimeException('Mercado Pago no creó la preferencia: HTTP '.$r->status().' '.mb_substr($r->body(), 0, 300));
        }

        return $r->json();
    }

    /** @return array{results: array, total: int} */
    public function buscarPagos(array $filtros, int $offset = 0, int $limit = 100): array
    {
        $r = $this->http()->get('/v1/payments/search', $filtros + [
            'sort' => 'date_created',
            'criteria' => 'asc',
            'offset' => $offset,
            'limit' => $limit,
        ]);

        if (! $r->successful()) {
            throw new RuntimeException("Mercado Pago respondió HTTP {$r->status()} al buscar pagos");
        }

        return [
            'results' => $r->json('results') ?? [],
            'total' => (int) ($r->json('paging.total') ?? 0),
        ];
    }

    public function pagosEntre(CarbonInterface $desde, CarbonInterface $hasta, int $maximo = 2000): array
    {
        $pagos = [];
        $offset = 0;

        do {
            $pagina = $this->buscarPagos([
                'range' => 'date_last_updated',
                'begin_date' => $desde->toIso8601String(),
                'end_date' => $hasta->toIso8601String(),
            ], $offset);
            $pagos = array_merge($pagos, $pagina['results']);
            $offset += 100;
        } while ($offset < min($pagina['total'], $maximo) && count($pagina['results']) > 0);

        return $pagos;
    }

    public function pagosPorReferencia(string $externalReference): array
    {
        return $this->buscarPagos(['external_reference' => $externalReference])['results'];
    }

    public function cuentaPropia(): array
    {
        $r = $this->http()->get('/users/me');
        if (! $r->successful()) {
            throw new RuntimeException("Mercado Pago respondió HTTP {$r->status()} en /users/me");
        }

        return $r->json();
    }

    private function http(): PendingRequest
    {
        $token = (string) config('billing.mercadopago.access_token');
        if ($token === '') {
            throw new RuntimeException('Falta MP_ACCESS_TOKEN en el entorno del hub de cobros.');
        }

        return Http::baseUrl((string) config('billing.mercadopago.api_base'))
            ->withToken($token)
            ->acceptJson()
            ->timeout((int) config('billing.mercadopago.timeout', 20))
            ->connectTimeout(10)
            ->retry(2, 400, fn ($e) => $e instanceof ConnectionException, throw: false);
    }
}
