<?php

namespace Tests\Feature\Billing;

use App\Billing\Mail\AvisoCobroMail;
use App\Billing\Models\LedgerEntry;
use App\Billing\Models\Payment;
use App\Billing\Services\PaymentProcessor;
use Illuminate\Support\Facades\Mail;

class PaymentProcessorTest extends BillingTestCase
{
    private function procesar(string $id, string $origen = 'webhook'): array
    {
        return app(PaymentProcessor::class)->procesarMercadoPago($id, $origen);
    }

    public function test_pago_aprobado_acredita_una_vez_y_envia_recibo(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('123456789', $this->checkout($c, 10000), 10000);

        $r = $this->procesar('123456789');

        $this->assertSame('acreditado', $r['resultado']);
        $this->assertSame(0, $this->saldo($c));
        $this->assertNotNull(Payment::where('external_id', '123456789')->value('acreditado_at'));
        Mail::assertSent(AvisoCobroMail::class, fn ($m) => $m->tipo === 'recibo');
    }

    public function test_mismo_pago_por_varias_vias_acredita_una_sola_vez(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('123456789', $this->checkout($c, 10000), 10000);

        $resultados = array_map(fn ($o) => $this->procesar('123456789', $o)['resultado'], ['webhook', 'retorno', 'conciliacion', 'webhook']);

        $this->assertSame(['acreditado', 'duplicado_ignorado', 'duplicado_ignorado', 'duplicado_ignorado'], $resultados);
        $this->assertSame(1, LedgerEntry::where('tipo', 'pago')->count());
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_pending_y_rejected_no_acreditan_y_pending_luego_aprobado_si(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $co = $this->checkout($c, 10000);

        $this->pagoMp('111111111', $co, 10000, 'rejected');
        $this->assertSame('no_aprobado', $this->procesar('111111111')['resultado']);

        $this->pagoMp('222222222', $co, 10000, 'pending');
        $this->assertSame('no_aprobado', $this->procesar('222222222')['resultado']);
        $this->assertSame(1000000, $this->saldo($c));

        $this->pagoMp('222222222', $co, 10000, 'approved');
        $this->assertSame('acreditado', $this->procesar('222222222')['resultado']);
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_rechaza_collector_ajeno_moneda_sandbox_y_referencia_desconocida(): void
    {
        $c = $this->cliente();
        $co = $this->checkout($c, 10000);

        $this->pagoMp('300000001', $co, 10000, 'approved', ['collector_id' => 999]);
        $this->pagoMp('300000002', $co, 10000, 'approved', ['currency_id' => 'USD']);
        $this->pagoMp('300000003', $co, 10000, 'approved', ['live_mode' => false]);
        $this->pagoMp('300000004', '44', 10000);
        $this->pagoMp('300000005', 'MB-no-existe', 10000);

        $this->assertSame('collector_ajeno', $this->procesar('300000001')['resultado']);
        $this->assertSame('moneda_invalida', $this->procesar('300000002')['resultado']);
        $this->assertSame('sandbox_rechazado', $this->procesar('300000003')['resultado']);
        $this->assertSame('referencia_desconocida', $this->procesar('300000004')['resultado']);
        $this->assertSame('referencia_desconocida', $this->procesar('300000005')['resultado']);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_pago_inexistente_o_id_invalido_no_acredita(): void
    {
        $this->assertSame('pago_inexistente', $this->procesar('888888888888')['resultado']);
        $this->assertSame('id_invalido', $this->procesar('abc; DROP')['resultado']);
        $this->assertSame(0, LedgerEntry::count());
    }

    public function test_recargo_del_checkout_se_registra_una_vez_por_periodo(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('400000001', $this->checkout($c, 10000, 1000), 11000);

        $this->procesar('400000001');

        $this->assertSame(1, LedgerEntry::where('tipo', 'recargo')->count());
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_importe_distinto_acredita_lo_cobrado_y_alerta(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('500000001', $this->checkout($c, 10000), 6000);

        $this->procesar('500000001');

        $this->assertSame(400000, $this->saldo($c));
        $this->assertDatabaseHas('billing_audit_logs', ['accion' => 'importe_distinto_checkout']);
    }

    public function test_reembolso_revierte_una_sola_vez(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $co = $this->checkout($c, 10000);
        $this->pagoMp('600000001', $co, 10000);
        $this->procesar('600000001');

        $this->pagoMp('600000001', $co, 10000, 'refunded');
        $this->assertSame('revertido', $this->procesar('600000001')['resultado']);
        $this->assertSame('reverso_duplicado', $this->procesar('600000001')['resultado']);
        $this->assertSame(1000000, $this->saldo($c));
    }

    public function test_pago_manual_con_misma_referencia_no_se_duplica(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $proc = app(PaymentProcessor::class);

        $this->assertNotNull($proc->registrarManual($c, 'transferencia', 1000000, now(), 'OP-777', 'Banco Nación', null));
        $this->assertNull($proc->registrarManual($c, 'transferencia', 1000000, now(), 'OP-777', 'Banco Nación', null));
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_libro_mayor_es_inmutable(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 100);
        $e = LedgerEntry::first();

        $this->expectException(\LogicException::class);
        $e->update(['importe_centavos' => 1]);
    }
}
