<?php

namespace Tests\Feature\Billing;

use App\Billing\Models\Checkout;
use App\Billing\Models\Customer;
use App\Billing\Models\Subscription;
use App\Billing\Services\LedgerService;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Tests\TestCase;

abstract class BillingTestCase extends TestCase
{
    use RefreshDatabase;

    protected const COLLECTOR = '2916747261';

    /** @var array<string, array> pagos simulados en Mercado Pago */
    protected array $pagosMp = [];

    protected int $preferenciasCreadas = 0;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'billing.public_url' => 'https://pagos.test',
            'billing.mercadopago.access_token' => 'TEST-TOKEN',
            'billing.mercadopago.webhook_secret' => 'secreto-webhook',
            'billing.mercadopago.collector_id' => self::COLLECTOR,
            'billing.avisos.canales' => ['mail'],
        ]);

        Mail::fake();
        $this->withoutVite();
        $this->fakeMercadoPago();
    }

    protected function fakeMercadoPago(): void
    {
        Http::fake(function ($request) {
            $url = $request->url();
            if (! str_starts_with($url, 'https://api.mercadopago.com')) {
                return null;
            }

            if (preg_match('#/v1/payments/(\d+)$#', $url, $m)) {
                return isset($this->pagosMp[$m[1]])
                    ? Http::response($this->pagosMp[$m[1]])
                    : Http::response(['message' => 'not found'], 404);
            }
            if (str_contains($url, '/v1/payments/search')) {
                parse_str((string) parse_url($url, PHP_URL_QUERY), $q);
                $res = array_values(array_filter($this->pagosMp, fn ($p) => ! isset($q['external_reference']) || $p['external_reference'] === $q['external_reference']));

                return Http::response(['results' => $res, 'paging' => ['total' => count($res)]]);
            }
            if (str_contains($url, '/checkout/preferences')) {
                $this->preferenciasCreadas++;
                $id = 'pref-'.Str::random(8);

                return Http::response(['id' => $id, 'init_point' => "https://mp.test/checkout?pref={$id}"], 201);
            }

            return Http::response([], 404);
        });
    }

    protected function cliente(array $attrs = [], int $abonoPesos = 10000): Customer
    {
        $c = Customer::create($attrs + ['nombre' => 'Comercio Test', 'email' => 'dueno@comercio.test', 'telefono' => '(260) 469-0410']);
        if ($abonoPesos > 0) {
            Subscription::create(['customer_id' => $c->id, 'concepto' => 'Abono mensual', 'importe_centavos' => $abonoPesos * 100, 'desde' => '2026-01-01', 'activa' => true]);
        }

        return $c;
    }

    protected function cargo(Customer $c, int $pesos, ?string $ref = null, string $fecha = '2026-11-01'): void
    {
        app(LedgerService::class)->registrar($c, 'cuota', $pesos * 100, $ref ?? 'test:'.Str::uuid(), 'Cargo de prueba', 'test', Carbon::parse($fecha));
    }

    protected function checkout(Customer $c, int $pesos, int $recargoPesos = 0): Checkout
    {
        return Checkout::create([
            'customer_id' => $c->id,
            'external_reference' => Checkout::PREFIJO.Str::uuid(),
            'preference_id' => 'pref-x',
            'init_point' => 'https://mp.test/x',
            'modo' => 'total',
            'importe_centavos' => ($pesos + $recargoPesos) * 100,
            'recargo_centavos' => $recargoPesos * 100,
            'recargo_periodo' => $recargoPesos ? '2026-11' : null,
            'estado' => 'abierto',
            'expira_at' => now()->addDay(),
        ]);
    }

    protected function pagoMp(string $id, Checkout|string $ref, float $monto, string $status = 'approved', array $extra = []): array
    {
        return $this->pagosMp[$id] = $extra + [
            'id' => (int) $id,
            'status' => $status,
            'status_detail' => $status === 'approved' ? 'accredited' : $status,
            'transaction_amount' => $monto,
            'currency_id' => 'ARS',
            'live_mode' => true,
            'collector_id' => (int) self::COLLECTOR,
            'external_reference' => $ref instanceof Checkout ? $ref->external_reference : $ref,
            'payment_method_id' => 'visa',
            'payment_type_id' => 'credit_card',
            'date_approved' => '2026-11-05T10:00:00.000-03:00',
            'date_created' => '2026-11-05T09:59:00.000-03:00',
            'transaction_details' => ['net_received_amount' => $monto * 0.94],
        ];
    }

    protected function saldo(Customer $c): int
    {
        return app(LedgerService::class)->saldo($c);
    }
}
