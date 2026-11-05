<?php

namespace Tests\Feature\Billing;

use App\Billing\Models\Checkout;
use App\Billing\Services\CheckoutService;
use Illuminate\Support\Facades\Http;

class CheckoutAndPayPageTest extends BillingTestCase
{
    public function test_checkout_se_reutiliza_si_no_cambia_el_importe(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $svc = app(CheckoutService::class);

        $a = $svc->obtener($c);
        $b = $svc->obtener($c);

        $this->assertSame($a->id, $b->id);
        $this->assertSame(1, $this->preferenciasCreadas);
        $this->assertStringStartsWith('MB-', $a->external_reference);
        Http::assertSent(fn ($r) => str_contains($r->url(), '/checkout/preferences')
            && $r->hasHeader('X-Idempotency-Key', $a->external_reference)
            && $r['external_reference'] === $a->external_reference
            && $r['notification_url'] === 'https://pagos.test/webhooks/mercadopago?source_news=webhooks'
            && $r['items'][0]['unit_price'] == 10000);
    }

    public function test_sin_deuda_no_crea_checkout(): void
    {
        $c = $this->cliente();
        $this->expectException(\DomainException::class);
        app(CheckoutService::class)->obtener($c);
    }

    public function test_pagina_publica_muestra_deuda_y_redirige_a_mercado_pago(): void
    {
        $c = $this->cliente(['nombre' => 'Kiosco Luna']);
        $this->cargo($c, 7500);

        $this->get("/pagar/{$c->pay_token}")->assertOk()->assertSee('Kiosco Luna')->assertSee('$7.500,00')->assertSee('Pagar con Mercado Pago');

        $r = $this->get("/pagar/{$c->pay_token}/ir?modo=total");
        $r->assertRedirect();
        $this->assertStringStartsWith('https://mp.test/checkout', $r->headers->get('Location'));
    }

    public function test_token_invalido_da_404(): void
    {
        $this->get('/pagar/'.str_repeat('x', 40))->assertNotFound();
        $this->get('/pagar/corto')->assertNotFound();
    }

    public function test_retorno_verifica_contra_api_y_no_confia_en_la_url(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $co = $this->checkout($c, 10000);

        $this->get("/pagar/{$c->pay_token}/resultado?status=approved&payment_id=999999999&external_reference={$co->external_reference}")
            ->assertOk()->assertDontSee('Recibimos $');
        $this->assertSame(1000000, $this->saldo($c));

        $this->pagoMp('800000001', $co, 10000);
        $this->get("/pagar/{$c->pay_token}/resultado?payment_id=800000001&external_reference={$co->external_reference}")
            ->assertOk()->assertSee('Recibimos $10.000,00');
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_retorno_con_checkout_de_otro_cliente_no_procesa(): void
    {
        $a = $this->cliente(['nombre' => 'A']);
        $b = $this->cliente(['nombre' => 'B']);
        $this->cargo($b, 10000);
        $coB = $this->checkout($b, 10000);
        $this->pagoMp('800000002', $coB, 10000);

        $this->get("/pagar/{$a->pay_token}/resultado?payment_id=800000002&external_reference={$coB->external_reference}")->assertOk();

        $this->assertSame(0, Checkout::where('estado', 'pagado')->count());
    }
}
