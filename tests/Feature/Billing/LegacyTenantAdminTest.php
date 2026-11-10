<?php

namespace Tests\Feature\Billing;

use App\Billing\Models\Customer;
use App\Billing\Models\LegacyDetalle;
use App\Billing\Services\LegacyImporter;
use App\Models\User;
use App\Services\Moon\MoonBillingClient;
use App\Services\Moon\MoonCobroService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;

class LegacyTenantAdminTest extends BillingTestCase
{
    private function legacyFake(): void
    {
        config(['database.connections.legacy_test' => ['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => ''],
            'billing.legacy.connection' => 'legacy_test']);
        $s = Schema::connection('legacy_test');
        $s->create('clientes', function ($t) {
            $t->integer('id');
            $t->text('nombre');
            $t->string('dominio')->nullable();
            $t->string('documento')->nullable();
            $t->text('email')->nullable();
            $t->text('telefono')->nullable();
            $t->text('direccion')->nullable();
            $t->string('tipo_documento')->nullable();
            $t->string('condicion_iva')->nullable();
            $t->integer('mensual');
            $t->text('observaciones')->nullable();
            $t->integer('estado_bloqueo')->default(0);
            $t->boolean('aplicar_recargos')->default(true);
        });
        $s->create('clientes_cuenta_corriente', function ($t) {
            $t->integer('id');
            $t->dateTime('fecha')->nullable();
            $t->integer('id_cliente');
            $t->integer('tipo');
            $t->text('descripcion')->nullable();
            $t->decimal('importe', 11, 2);
            $t->text('metodo_pago')->nullable();
            $t->string('numero_recibo')->nullable();
            $t->string('referencia_mp')->nullable();
            $t->string('origen')->nullable();
        });
        $db = DB::connection('legacy_test');
        $db->table('clientes')->insert([
            ['id' => 1, 'nombre' => 'Consumidor final', 'mensual' => 0, 'email' => null, 'documento' => '0', 'dominio' => null, 'telefono' => null, 'direccion' => null, 'tipo_documento' => null, 'condicion_iva' => null],
            ['id' => 19, 'nombre' => 'JAMROD (Marta James)', 'mensual' => 7500, 'email' => 'x', 'documento' => '20-27892455-7', 'dominio' => 'jamrod.posmoon.com', 'telefono' => '(260) 469-0410', 'direccion' => 'San Martín 123', 'tipo_documento' => 'CUIT', 'condicion_iva' => 'MONOTRIBUTO'],
            ['id' => 20, 'nombre' => 'ABISKO', 'mensual' => 8000, 'email' => 'a@abisko.com', 'documento' => '', 'dominio' => 'abisko.posmoon.com', 'telefono' => '', 'direccion' => null, 'tipo_documento' => null, 'condicion_iva' => null],
        ]);
        $db->table('clientes_cuenta_corriente')->insert([
            ['id' => 10, 'fecha' => '2026-09-01 04:00:00', 'id_cliente' => 19, 'tipo' => 0, 'descripcion' => 'Servicio POS septiembre/2026', 'importe' => 7500, 'metodo_pago' => null, 'numero_recibo' => null, 'referencia_mp' => null, 'origen' => null],
            ['id' => 11, 'fecha' => '2026-10-01 04:00:00', 'id_cliente' => 19, 'tipo' => 0, 'descripcion' => 'Servicio POS octubre/2026', 'importe' => 7500, 'metodo_pago' => null, 'numero_recibo' => null, 'referencia_mp' => null, 'origen' => null],
            ['id' => 12, 'fecha' => '2026-09-05 10:00:00', 'id_cliente' => 19, 'tipo' => 1, 'descripcion' => 'Pago', 'importe' => 7500, 'metodo_pago' => 'TR--', 'numero_recibo' => '155', 'referencia_mp' => 'MP-99', 'origen' => 'manual'],
            ['id' => 13, 'fecha' => '2026-10-01 04:00:00', 'id_cliente' => 20, 'tipo' => 0, 'descripcion' => 'Servicio POS octubre/2026', 'importe' => 0, 'metodo_pago' => null, 'numero_recibo' => null, 'referencia_mp' => null, 'origen' => null],
            ['id' => 14, 'fecha' => '2026-10-01 04:00:00', 'id_cliente' => 1, 'tipo' => 0, 'descripcion' => 'x', 'importe' => 100, 'metodo_pago' => null, 'numero_recibo' => null, 'referencia_mp' => null, 'origen' => null],
        ]);
    }

    public function test_import_legacy_es_idempotente_y_saldos_coinciden(): void
    {
        $this->legacyFake();
        $imp = app(LegacyImporter::class);

        $r1 = $imp->importar();
        $r2 = $imp->importar();

        $this->assertSame([2, 3, []], [$r1['clientes_nuevos'], $r1['movimientos'], $r1['saldos_distintos']]);
        $this->assertSame([0, 0, []], [$r2['clientes_nuevos'], $r2['movimientos'], $r2['saldos_distintos']]);

        $jamrod = Customer::where('legacy_id', 19)->first();
        $this->assertSame(750000, $this->saldo($jamrod));
        $this->assertSame('20278924557', $jamrod->cuit);
        $this->assertSame('20-27892455-7', $jamrod->documento);
        $this->assertSame('San Martín 123', $jamrod->direccion);
        $this->assertSame('CUIT', $jamrod->tipo_documento);
        $this->assertNull($jamrod->email);
        $det = LegacyDetalle::where('legacy_movimiento_id', 12)->first();
        $this->assertSame('TR--', $det->metodo_pago);
        $this->assertSame('155', $det->numero_recibo);
        $this->assertSame('MP-99', $det->referencia_mp);
        $this->assertSame(1, LegacyDetalle::where('legacy_movimiento_id', 12)->count());

        $admin = User::factory()->create(['es_superadmin' => true]);
        $this->actingAs($admin)->get('/cobros/pagos')->assertOk()->assertSee('JAMROD')->assertSee('TR--')->assertSee('MP-99');
        $this->actingAs($admin)->get('/cobros/clientes/'.$jamrod->id)->assertOk()
            ->assertSee('wa.me/5492604690410', false)
            ->assertSee('20-27892455-7')
            ->assertSee('San Martín 123');
        $this->assertSame('solo_aviso', $jamrod->modo_cobro);
        $this->assertSame(750000, $jamrod->abonoMensualCentavos());
        $this->assertNull(Customer::where('legacy_id', 1)->first());
    }

    public function test_import_compensa_ediciones_y_borrados_del_legacy(): void
    {
        $this->legacyFake();
        $imp = app(LegacyImporter::class);
        $imp->importar();

        DB::connection('legacy_test')->table('clientes_cuenta_corriente')->where('id', 11)->update(['importe' => 9000]);
        DB::connection('legacy_test')->table('clientes_cuenta_corriente')->where('id', 12)->delete();
        $r = $imp->importar();

        $this->assertSame(2, $r['diferencias']);
        $this->assertSame([], $r['saldos_distintos']);
        $this->assertSame(1650000, $this->saldo(Customer::where('legacy_id', 19)->first()));
    }

    public function test_api_tenant_con_key_valida_e_invalida(): void
    {
        $c = $this->cliente(['nombre' => 'JAMROD']);
        $this->cargo($c, 7500);
        $key = $c->regenerarApiKey();

        $this->getJson('/api/billing/v1/estado', ['Authorization' => 'Bearer '.$key])
            ->assertOk()->assertJsonPath('cliente', 'JAMROD')->assertJsonPath('pay_url', $c->payUrl());
        $this->getJson('/api/billing/v1/estado', ['Authorization' => 'Bearer mbk_falsa'])->assertStatus(401);
        $this->getJson('/api/billing/v1/estado')->assertStatus(401);
    }

    public function test_tenant_usa_el_hub_con_fail_open_y_respaldo(): void
    {
        config(['moon.billing.url' => 'https://hub.test', 'moon.billing.key' => 'mbk_x', 'moon.billing.enforce' => true, 'cache.default' => 'array']);
        Http::fake(['hub.test/*' => Http::sequence()
            ->push(['nivel' => 'suspendido', 'bloquear' => true, 'saldo' => 8250, 'mensaje' => 'Suspendido', 'pay_url' => 'https://pagos/x'])
            ->push('caído', 500)]);

        $lic = app(MoonCobroService::class)->evaluarLicencia(19);
        $this->assertSame([false, 'blocked', 'https://pagos/x'], [$lic['can_sell'], $lic['level'], $lic['pay_url']]);

        app(MoonBillingClient::class)->olvidar();
        $respaldo = app(MoonBillingClient::class)->estado();
        $this->assertSame('respaldo', $respaldo['fuente']);

        app(MoonBillingClient::class)->olvidar();
        Cache::flush();
        $this->assertTrue(app(MoonCobroService::class)->evaluarLicencia(19)['can_sell']);
    }

    public function test_sin_enforce_el_tenant_nunca_bloquea(): void
    {
        config(['moon.billing.url' => 'https://hub.test', 'moon.billing.key' => 'mbk_x', 'moon.billing.enforce' => false, 'cache.default' => 'array']);
        Http::fake(['hub.test/*' => Http::response(['nivel' => 'suspendido', 'bloquear' => true, 'saldo' => 1, 'mensaje' => 'x', 'pay_url' => null])]);

        $this->assertTrue(app(MoonCobroService::class)->evaluarLicencia(19)['can_sell']);
    }

    public function test_panel_solo_superadmin_y_pago_manual(): void
    {
        $c = $this->cliente(['nombre' => 'Panel Test']);
        $this->cargo($c, 10000);
        $comun = User::factory()->create(['es_superadmin' => false]);
        $admin = User::factory()->create(['es_superadmin' => true]);

        $this->get('/cobros')->assertRedirect();
        $this->actingAs($comun)->get('/cobros')->assertForbidden();

        $this->actingAs($admin)->get('/cobros')->assertOk()->assertSee('Deuda total');
        $this->actingAs($admin)->get('/cobros/clientes')->assertOk()->assertSee('Panel Test');
        $this->actingAs($admin)->get("/cobros/clientes/{$c->id}")->assertOk();
        $this->actingAs($admin)->get('/cobros/pagos')->assertOk();
        $this->actingAs($admin)->get('/cobros/eventos')->assertOk();

        $this->actingAs($admin)->post("/cobros/clientes/{$c->id}/pago", [
            'metodo' => 'transferencia', 'importe' => '10.000', 'fecha' => now()->toDateString(), 'referencia' => 'OP1',
        ])->assertRedirect()->assertSessionHas('ok');
        $this->assertSame(0, $this->saldo($c));

        $this->actingAs($admin)->post("/cobros/clientes/{$c->id}/ajuste", ['importe' => '-500', 'motivo' => 'Descuento comercial'])->assertSessionHas('ok');
        $this->assertSame(-50000, $this->saldo($c));
    }
}
