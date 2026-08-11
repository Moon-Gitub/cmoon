<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiV1ProductosTest extends TestCase
{
    use RefreshDatabase;

    public function test_requiere_token_para_listar_productos(): void
    {
        $this->getJson('/api/v1/productos')
            ->assertUnauthorized();
    }

    public function test_login_y_listado_de_productos(): void
    {
        $empresa = Empresa::query()->create([
            'razon_social' => 'Empresa Test',
            'cuit' => '30111111111',
            'activa' => true,
        ]);

        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'usuario' => 'apiuser',
            'password' => 'password',
            'activo' => true,
        ]);

        Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'SKU-1',
            'nombre' => 'Producto API',
            'precio_venta' => 100,
            'precio_compra' => 50,
            'unidad' => 'UN',
            'activo' => true,
        ]);

        $login = $this->postJson('/api/v1/auth/token', [
            'usuario' => 'apiuser',
            'password' => 'password',
            'device_name' => 'phpunit',
        ])->assertOk()
            ->assertJsonStructure(['token', 'token_type', 'user']);

        $token = $login->json('token');

        $this->withToken($token)
            ->getJson('/api/v1/productos')
            ->assertOk()
            ->assertJsonPath('data.0.codigo', 'SKU-1');
    }

    public function test_shopify_webhook_rechaza_hmac_invalido(): void
    {
        $empresa = Empresa::query()->create([
            'razon_social' => 'Empresa SH',
            'cuit' => '30222222222',
            'activa' => true,
        ]);

        \App\Models\ShopifyIntegracion::query()->create([
            'empresa_id' => $empresa->id,
            'store_domain' => 'demo.myshopify.com',
            'access_token' => 'shpat_test',
            'api_secret' => 'secret-de-prueba',
            'activo' => true,
            'sync_orders' => true,
        ]);

        $body = json_encode(['id' => 1, 'name' => '#1001']);

        $this->call(
            'POST',
            '/webhooks/shopify',
            [],
            [],
            [],
            [
                'CONTENT_TYPE' => 'application/json',
                'HTTP_X_SHOPIFY_TOPIC' => 'orders/paid',
                'HTTP_X_SHOPIFY_SHOP_DOMAIN' => 'demo.myshopify.com',
                'HTTP_X_SHOPIFY_HMAC_SHA256' => 'firma-invalida',
            ],
            $body
        )->assertStatus(401);
    }
}
