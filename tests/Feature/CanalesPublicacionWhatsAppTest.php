<?php

namespace Tests\Feature;

use App\Jobs\Shopify\SyncProductToShopify;
use App\Jobs\WhatsApp\ResponderConsultaWhatsApp;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\ShopifyIntegracion;
use App\Models\YcloudIntegracion;
use App\Services\ProductoConsultaIaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

class CanalesPublicacionWhatsAppTest extends TestCase
{
    use RefreshDatabase;

    public function test_consulta_solo_usa_productos_marcados_en_whatsapp(): void
    {
        $empresa = $this->empresa();

        Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'CALZA-1',
            'nombre' => 'Calza negra Electra',
            'precio_venta' => 30000,
            'precio_compra' => 10000,
            'unidad' => 'UN',
            'activo' => true,
            'publicar_whatsapp' => true,
        ]);

        Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'CALZA-2',
            'nombre' => 'Calza negra interna',
            'precio_venta' => 1000,
            'precio_compra' => 500,
            'unidad' => 'UN',
            'activo' => true,
            'publicar_whatsapp' => false,
        ]);

        $r = app(ProductoConsultaIaService::class)->responder($empresa->id, 'tenés calza negra?');

        $this->assertNotEmpty($r['producto_ids']);
        $this->assertStringContainsString('Electra', $r['texto']);
        $this->assertStringNotContainsString('interna', $r['texto']);
    }

    public function test_webhook_ycloud_encola_respuesta(): void
    {
        Queue::fake();
        $empresa = $this->empresa();

        YcloudIntegracion::query()->create([
            'empresa_id' => $empresa->id,
            'api_key' => 'test-key',
            'phone_from' => '+5491100000000',
            'activo' => true,
            'bot_activo' => true,
            'auto_reply' => true,
        ]);

        $this->postJson('/webhooks/ycloud', [
            'type' => 'whatsapp.inbound_message.received',
            'whatsappInboundMessage' => [
                'id' => 'wim1',
                'from' => '+5491199999999',
                'to' => '+5491100000000',
                'type' => 'text',
                'text' => ['body' => 'hola, catalogo'],
            ],
        ])->assertOk();

        Queue::assertPushed(ResponderConsultaWhatsApp::class);
    }

    public function test_push_shopify_omite_producto_sin_flag(): void
    {
        Http::fake();
        $empresa = $this->empresa();

        $integracion = ShopifyIntegracion::query()->create([
            'empresa_id' => $empresa->id,
            'store_domain' => 'demo.myshopify.com',
            'access_token' => 'shpat_test',
            'activo' => true,
            'push_products' => true,
        ]);

        $producto = Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'NO-SH',
            'nombre' => 'No va a Shopify',
            'precio_venta' => 10,
            'precio_compra' => 5,
            'unidad' => 'UN',
            'activo' => true,
            'publicar_shopify' => false,
        ]);

        (new SyncProductToShopify($integracion, $producto))->handle();

        Http::assertNothingSent();
    }

    private function empresa(): Empresa
    {
        return Empresa::query()->create([
            'razon_social' => 'Empresa Canales',
            'cuit' => '30999888776',
            'activa' => true,
        ]);
    }
}
