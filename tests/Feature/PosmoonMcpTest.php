<?php

namespace Tests\Feature;

use App\Mcp\Servers\PosmoonServer;
use App\Mcp\Tools\EmpresaResumenTool;
use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class PosmoonMcpTest extends TestCase
{
    use RefreshDatabase;

    public function test_mcp_exige_bearer(): void
    {
        $this->postJson('/mcp', $this->rpc('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass,
            'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
        ]))->assertUnauthorized();
    }

    public function test_token_api_no_accede_al_mcp(): void
    {
        $user = $this->userConEmpresa();
        $token = $user->createToken('api', ['api'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/mcp', $this->rpc('initialize', [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
            ]))
            ->assertForbidden();
    }

    public function test_initialize_y_tools_con_token_mcp(): void
    {
        $user = $this->userConEmpresa();
        $token = $user->createToken('mcp', ['mcp:read'])->plainTextToken;

        $init = $this->withToken($token)
            ->postJson('/mcp', $this->rpc('initialize', [
                'protocolVersion' => '2025-11-25',
                'capabilities' => new \stdClass,
                'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
            ]));

        $init->assertOk()
            ->assertJsonPath('result.serverInfo.name', 'POSMoon');

        $list = $this->withToken($token)
            ->postJson('/mcp', $this->rpc('tools/list'));

        $list->assertOk();
        $nombres = collect($list->json('result.tools'))->pluck('name');
        $this->assertTrue($nombres->contains('empresa_resumen'));
        $this->assertTrue($nombres->contains('buscar_productos'));
        $this->assertTrue($nombres->contains('listar_ventas'));
    }

    public function test_empresa_resumen_via_servidor(): void
    {
        $user = $this->userConEmpresa();

        PosmoonServer::actingAs($user)
            ->tool(EmpresaResumenTool::class)
            ->assertOk()
            ->assertSee('ventas_hoy')
            ->assertSee('MCP SA');
    }

    public function test_no_filtra_productos_de_otra_empresa(): void
    {
        $user = $this->userConEmpresa();
        $otra = Empresa::query()->create([
            'razon_social' => 'Otra SA',
            'cuit' => '30999999999',
            'activa' => true,
        ]);
        Producto::query()->create([
            'empresa_id' => $otra->id,
            'codigo' => 'AJENO',
            'nombre' => 'Producto ajeno',
            'precio_venta' => 10,
            'precio_compra' => 5,
            'unidad' => 'UN',
            'activo' => true,
        ]);
        Producto::query()->create([
            'empresa_id' => $user->empresa_id,
            'codigo' => 'MIO',
            'nombre' => 'Producto mio',
            'precio_venta' => 20,
            'precio_compra' => 8,
            'unidad' => 'UN',
            'activo' => true,
        ]);

        $token = $user->createToken('mcp', ['mcp:read'])->plainTextToken;

        $res = $this->withToken($token)
            ->postJson('/mcp', $this->rpc('tools/call', [
                'name' => 'buscar_productos',
                'arguments' => ['q' => 'Producto', 'limit' => 20],
            ]));

        $res->assertOk();
        $texto = (string) data_get($res->json(), 'result.content.0.text');
        $this->assertStringContainsString('MIO', $texto);
        $this->assertStringNotContainsString('AJENO', $texto);
    }

    public function test_token_mcp_no_puede_escribir_por_api(): void
    {
        $user = $this->userConEmpresa();
        $token = $user->createToken('mcp', ['mcp:read'])->plainTextToken;

        $this->withToken($token)
            ->postJson('/api/v1/ventas', [
                'sucursal_id' => 1,
                'items' => [['producto_id' => 1, 'cantidad' => 1, 'precio_unitario' => 10]],
                'pagos' => [['medio_pago_id' => 1, 'importe' => 10]],
            ])
            ->assertForbidden();
    }

    public function test_sesion_web_no_entra_al_mcp(): void
    {
        $user = $this->userConEmpresa();
        Sanctum::actingAs($user);

        $this->postJson('/mcp', $this->rpc('initialize', [
            'protocolVersion' => '2025-11-25',
            'capabilities' => new \stdClass,
            'clientInfo' => ['name' => 'phpunit', 'version' => '1'],
        ]))->assertUnauthorized();
    }

    private function userConEmpresa(): User
    {
        $empresa = Empresa::query()->create([
            'razon_social' => 'MCP SA',
            'cuit' => '30123456789',
            'activa' => true,
        ]);

        return User::factory()->create([
            'empresa_id' => $empresa->id,
            'usuario' => 'mcpuser',
            'password' => 'password',
            'activo' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $params
     * @return array<string, mixed>
     */
    private function rpc(string $method, array $params = []): array
    {
        $payload = [
            'jsonrpc' => '2.0',
            'id' => 1,
            'method' => $method,
        ];
        if ($params !== []) {
            $payload['params'] = $params;
        }

        return $payload;
    }
}
