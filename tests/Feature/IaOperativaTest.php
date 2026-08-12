<?php

namespace Tests\Feature;

use App\Models\Empresa;
use App\Models\Producto;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IaOperativaTest extends TestCase
{
    use RefreshDatabase;

    public function test_sugerir_canales_gratis_marca_solo_vendibles(): void
    {
        $empresa = Empresa::query()->create([
            'razon_social' => 'IA Op',
            'cuit' => '30111222333',
            'activa' => true,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'usuario' => 'iaop',
            'password' => 'password',
            'activo' => true,
        ]);
        $user->givePermissionTo('productos.editar');

        $sucursal = \App\Models\Sucursal::withoutGlobalScopes()->create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Casa',
            'activa' => true,
        ]);

        $ok = Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'OK-1',
            'nombre' => 'Remera negra M',
            'precio_venta' => 1000,
            'precio_compra' => 400,
            'unidad' => 'UN',
            'activo' => true,
        ]);
        $ok->stocks()->create(['sucursal_id' => $sucursal->id, 'cantidad' => 5]);

        $malo = Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'BAD',
            'nombre' => '12',
            'precio_venta' => 0,
            'precio_compra' => 0,
            'unidad' => 'UN',
            'activo' => true,
        ]);

        $this->actingAs($user);
        $request = \Illuminate\Http\Request::create('/ia/productos/sugerir-canales', 'POST', [
            'ids' => [$ok->id, $malo->id],
        ]);
        $data = app(\App\Http\Controllers\IaOperativaController::class)
            ->sugerirCanales($request)
            ->getData(true);

        $this->assertTrue($data['gratis']);
        $byId = collect($data['sugeridos'])->keyBy('id');
        $this->assertTrue($byId[$ok->id]['publicar_whatsapp'], json_encode($data));
        $this->assertFalse($byId[$malo->id]['publicar_whatsapp']);
    }
}
