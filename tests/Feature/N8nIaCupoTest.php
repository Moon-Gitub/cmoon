<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\N8nIntegracion;
use App\Services\IaCupoService;
use App\Services\N8nService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class N8nIaCupoTest extends TestCase
{
    use RefreshDatabase;

    public function test_cupo_incluido_bloquea_al_exceder(): void
    {
        config(['ia.cupo_incluido' => 2]);
        $empresa = $this->empresa();
        $cupo = app(IaCupoService::class);

        $this->assertTrue($cupo->consumir($empresa->id));
        $this->assertTrue($cupo->consumir($empresa->id));
        $this->assertFalse($cupo->consumir($empresa->id));
        $this->assertSame(0, $cupo->resumen($empresa->id)['restantes']);
    }

    public function test_abono_usa_cupo_mayor(): void
    {
        config(['ia.cupo_incluido' => 2, 'ia.cupo_abono' => 10]);
        $empresa = $this->empresa();
        $empresa->update(['ia_plan' => 'abono', 'ia_abono_hasta' => now()->addMonth()]);

        $this->assertSame(10, app(IaCupoService::class)->resumen($empresa->id)['cupo']);
    }

    public function test_webhook_n8n_exige_secret(): void
    {
        $empresa = $this->empresa();
        N8nIntegracion::query()->create([
            'empresa_id' => $empresa->id,
            'inbound_secret' => 'secreto-n8n',
            'activo' => true,
        ]);

        $this->postJson('/webhooks/n8n', ['accion' => 'ping', 'empresa_id' => $empresa->id])
            ->assertUnauthorized();

        $this->postJson('/webhooks/n8n', [
            'accion' => 'ping',
            'empresa_id' => $empresa->id,
        ], ['X-N8N-Secret' => 'secreto-n8n'])
            ->assertOk()
            ->assertJsonPath('data.pong', true);
    }

    public function test_emite_evento_a_url_n8n(): void
    {
        Http::fake([
            'https://n8n.example.test/*' => Http::response(['ok' => true], 200),
        ]);

        $empresa = $this->empresa();
        $int = N8nIntegracion::query()->create([
            'empresa_id' => $empresa->id,
            'webhooks' => ['custom' => 'https://n8n.example.test/webhook/abc'],
            'activo' => true,
        ]);

        $r = app(N8nService::class)->enviarAhora($int, 'custom', ['hola' => 1]);

        $this->assertTrue($r['ok']);
        Http::assertSent(fn ($req) => $req->url() === 'https://n8n.example.test/webhook/abc'
            && $req['event'] === 'custom');
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'razon_social' => 'Empresa n8n',
            'cuit' => '30888777665',
            'activa' => true,
        ]);
    }
}
