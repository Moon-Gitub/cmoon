<?php

namespace Tests\Feature\Billing;

use App\Billing\Models\Invoice;
use App\Billing\Models\LedgerEntry;
use App\Billing\Models\Subscription;
use App\Billing\Services\CustomerStatusService;
use App\Billing\Services\InvoiceGenerator;
use App\Billing\Services\PaymentProcessor;
use Carbon\Carbon;

class PolicyAndInvoicesTest extends BillingTestCase
{
    private function estado($c, string $fecha): array
    {
        return app(CustomerStatusService::class)->estado($c, Carbon::parse($fecha, 'America/Argentina/Mendoza'));
    }

    public function test_generar_cuotas_es_idempotente_y_omite_abono_cero_y_bajas(): void
    {
        $a = $this->cliente(['nombre' => 'A']);
        $this->cliente(['nombre' => 'Cero'], 0);
        $this->cliente(['nombre' => 'Baja', 'estado' => 'baja']);
        $gen = app(InvoiceGenerator::class);
        $mes = Carbon::parse('2026-11-01');

        $this->assertSame(['creadas' => 1, 'existentes' => 0], $gen->generar($mes));
        $this->assertSame(['creadas' => 0, 'existentes' => 1], $gen->generar($mes));

        $this->assertSame(1, Invoice::count());
        $this->assertSame(1000000, $this->saldo($a));
        $this->assertSame(1, LedgerEntry::where('tipo', 'cuota')->count());
    }

    public function test_suscripcion_fuera_de_vigencia_no_genera(): void
    {
        $c = $this->cliente([], 0);
        Subscription::create(['customer_id' => $c->id, 'concepto' => 'Prepago', 'importe_centavos' => 500000, 'desde' => '2027-01-01', 'activa' => true]);

        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));

        $this->assertSame(0, Invoice::count());
    }

    public function test_recargos_por_dia_y_exento(): void
    {
        $c = $this->cliente();
        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));

        $this->assertSame([0, 'pendiente'], [$this->estado($c, '2026-11-05')['recargo'], $this->estado($c, '2026-11-05')['nivel']]);
        $this->assertSame([100000, 'vencido'], [$this->estado($c, '2026-11-12')['recargo'], $this->estado($c, '2026-11-12')['nivel']]);
        $this->assertSame(150000, $this->estado($c, '2026-11-22')['recargo']);

        $c->update(['aplicar_recargos' => false]);
        $this->assertSame(0, $this->estado($c->fresh(), '2026-11-22')['recargo']);
    }

    public function test_recargo_ya_aplicado_en_el_periodo_no_se_vuelve_a_cobrar(): void
    {
        $c = $this->cliente();
        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));
        $this->cargo($c, 1000, CustomerStatusService::referenciaRecargo($c, '2026-11'));

        $this->assertSame(0, $this->estado($c, '2026-11-15')['recargo']);
    }

    public function test_suspension_solo_con_modo_bloqueo_y_sin_prorroga(): void
    {
        $c = $this->cliente(['modo_cobro' => 'bloqueo']);
        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));

        $this->assertSame('por_suspender', $this->estado($c, '2026-11-22')['nivel']);
        $e = $this->estado($c, '2026-11-26');
        $this->assertSame(['suspendido', true], [$e['nivel'], $e['bloquear']]);

        $c->update(['prorroga_hasta' => '2026-11-30']);
        $e = $this->estado($c->fresh(), '2026-11-27');
        $this->assertSame(['moroso', false], [$e['nivel'], $e['bloquear']]);

        $c->update(['prorroga_hasta' => null, 'modo_cobro' => 'solo_aviso']);
        $this->assertFalse($this->estado($c->fresh(), '2026-11-27')['bloquear']);
    }

    public function test_deuda_de_meses_anteriores_suspende_aunque_sea_principio_de_mes(): void
    {
        $c = $this->cliente(['modo_cobro' => 'bloqueo']);
        $gen = app(InvoiceGenerator::class);
        $gen->generar(Carbon::parse('2026-10-01'));
        $gen->generar(Carbon::parse('2026-11-01'));

        $this->assertTrue($this->estado($c, '2026-11-03')['bloquear']);
    }

    public function test_pagar_desbloquea_al_instante_y_tolerancia(): void
    {
        $c = $this->cliente(['modo_cobro' => 'bloqueo']);
        app(InvoiceGenerator::class)->generar(Carbon::parse('2026-11-01'));
        $this->assertTrue($this->estado($c, '2026-11-27')['bloquear']);

        app(PaymentProcessor::class)->registrarManual($c, 'transferencia', 999950, now(), 'X1', null, null);

        $e = $this->estado($c, '2026-11-27');
        $this->assertSame(['al_dia', false], [$e['nivel'], $e['bloquear']]);
    }
}
