<?php

namespace Tests\Feature\Billing;

use App\Billing\Models\WebhookEvent;
use App\Billing\Services\WebhookSignature;

class WebhookTest extends BillingTestCase
{
    private function headers(string $dataId, ?int $ts = null, string $secret = 'secreto-webhook'): array
    {
        $ts = (string) ($ts ?? time());
        $reqId = 'req-'.$dataId;

        return [
            'x-request-id' => $reqId,
            'x-signature' => 'ts='.$ts.',v1='.WebhookSignature::firmar($secret, $dataId, $reqId, $ts),
        ];
    }

    public function test_webhook_firmado_acredita_el_pago(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('700000001', $this->checkout($c, 10000), 10000);

        $this->postJson('/webhooks/mercadopago?data.id=700000001&type=payment', [
            'type' => 'payment', 'data' => ['id' => '700000001'],
        ], $this->headers('700000001'))->assertOk();

        $this->assertSame(0, $this->saldo($c));
        $this->assertNotNull(WebhookEvent::first()->procesado_at);
    }

    public function test_firma_invalida_o_vencida_responde_401_y_no_procesa(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('700000002', $this->checkout($c, 10000), 10000);

        $this->postJson('/webhooks/mercadopago?data.id=700000002&type=payment', ['type' => 'payment', 'data' => ['id' => '700000002']],
            $this->headers('700000002', secret: 'otro'))->assertStatus(401);
        $this->postJson('/webhooks/mercadopago?data.id=700000002&type=payment', ['type' => 'payment', 'data' => ['id' => '700000002']],
            $this->headers('700000002', time() - 3600))->assertStatus(401);
        $this->postJson('/webhooks/mercadopago?data.id=700000002&type=payment', ['type' => 'payment'])->assertStatus(401);

        $this->assertSame(1000000, $this->saldo($c));
        $this->assertSame(3, WebhookEvent::where('firma_valida', false)->count());
    }

    public function test_topic_desconocido_se_ignora_con_200(): void
    {
        $this->postJson('/webhooks/mercadopago?data.id=55555&type=subscription_preapproval', ['type' => 'subscription_preapproval'],
            $this->headers('55555'))->assertOk();

        $this->assertSame('topic_ignorado', WebhookEvent::first()->error);
    }
}
