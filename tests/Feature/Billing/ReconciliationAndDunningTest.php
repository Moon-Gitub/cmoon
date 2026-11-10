<?php

namespace Tests\Feature\Billing;

use App\Billing\Mail\AvisoCobroMail;
use App\Billing\Models\Notification;
use App\Billing\Services\DunningService;
use App\Models\User;
use App\Billing\Services\InvoiceGenerator;
use App\Billing\Services\LedgerService;
use App\Billing\Services\PaymentProcessor;
use App\Billing\Services\ReconciliationService;
use Carbon\Carbon;
use Illuminate\Support\Facades\Mail;

class ReconciliationAndDunningTest extends BillingTestCase
{
    public function test_conciliacion_acredita_pago_no_notificado_y_reporta(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('900000001', $this->checkout($c, 10000), 10000);

        $r = app(ReconciliationService::class)->conciliar(3);

        $this->assertSame(1, $r->acreditados);
        $this->assertSame('ok', $r->estado);
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_conciliacion_alerta_acreditacion_sin_respaldo(): void
    {
        $c = $this->cliente();
        app(LedgerService::class)->registrar($c, 'pago', -500000, 'mp:900000099', 'Pago fantasma', 'test', now());

        $r = app(ReconciliationService::class)->conciliar(3);

        $this->assertSame('diferencias', $r->estado);
        $this->assertSame('acreditacion_sin_respaldo', $r->alertas[0]['tipo']);
    }

    public function test_barrido_de_pendientes_rescata_pago_sin_webhook(): void
    {
        $c = $this->cliente();
        $this->cargo($c, 10000);
        $this->pagoMp('900000002', $this->checkout($c, 10000), 10000);

        $this->assertSame(1, app(ReconciliationService::class)->sincronizarPendientes());
        $this->assertSame(0, $this->saldo($c));
    }

    public function test_avisos_se_envian_una_sola_vez_por_tipo_y_periodo(): void
    {
        config(['billing.avisos.enabled' => true]);
        $deudor = $this->cliente(['nombre' => 'Deudor']);
        $alDia = $this->cliente(['nombre' => 'Al día', 'email' => 'aldia@test.com']);
        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));
        app(PaymentProcessor::class)->registrarManual($alDia, 'efectivo', 1000000, now(), 'E1', null, null);
        Mail::fake();

        $svc = app(DunningService::class);
        $r1 = $svc->ejecutar(Carbon::parse('2026-11-08'));
        $r2 = $svc->ejecutar(Carbon::parse('2026-11-08'));

        $this->assertSame(['enviados' => 1, 'omitidos' => 0, 'fallidos' => 0, 'tipo' => 'recordatorio'], $r1);
        $this->assertSame(1, $r2['omitidos']);
        Mail::assertSent(AvisoCobroMail::class, 1);
        $this->assertSame(1, Notification::where('customer_id', $deudor->id)->count());
    }

    public function test_aviso_de_suspension_solo_a_modo_bloqueo_y_dia_sin_aviso_no_envia(): void
    {
        config(['billing.avisos.enabled' => true]);
        $this->cliente(['nombre' => 'Solo aviso']);
        $this->cliente(['nombre' => 'Bloqueo', 'modo_cobro' => 'bloqueo', 'email' => 'b@test.com']);
        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));

        $this->assertSame(1, app(DunningService::class)->ejecutar(Carbon::parse('2026-11-22'))['enviados']);
        $this->assertNull(app(DunningService::class)->ejecutar(Carbon::parse('2026-11-23'))['tipo']);
    }

    public function test_celular_argentino_se_normaliza_para_whatsapp(): void
    {
        $this->assertSame('5492604690410', DunningService::celularArgentino('(260) 469-0410'));
        $this->assertSame('5492604690410', DunningService::celularArgentino('+54 260 4690410'));
        $this->assertNull(DunningService::celularArgentino('(213) 213'));
    }

    public function test_cola_de_avisos_lista_deudores_sin_enviar(): void
    {
        config(['billing.avisos.enabled' => false]);
        $c = $this->cliente(['nombre' => 'Deudor Aviso', 'telefono' => '(260) 469-0410']);
        $this->cargo($c, 8000);

        $cola = app(DunningService::class)->cola(Carbon::parse('2026-11-10'));

        $this->assertNull($cola['programado']);
        $this->assertCount(1, $cola['filas']);
        $this->assertSame('vencido', $cola['filas'][0]['tipo']);
        $this->assertStringStartsWith('https://wa.me/5492604690410?text=', $cola['filas'][0]['whatsapp']);
        $this->assertSame(0, Notification::count());

        $admin = User::factory()->create(['es_superadmin' => true]);
        $this->actingAs($admin)->get('/cobros/avisos')->assertOk()
            ->assertSee('Deudor Aviso')
            ->assertSee('wa.me/5492604690410', false)
            ->assertSee('no envía nada');
    }
}
