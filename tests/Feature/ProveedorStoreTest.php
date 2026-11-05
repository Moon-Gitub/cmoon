<?php

namespace Tests\Feature;

use App\Http\Controllers\ProveedorController;
use App\Models\Empresa;
use App\Models\Proveedor;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Tests\TestCase;

class ProveedorStoreTest extends TestCase
{
    use RefreshDatabase;

    public function test_puede_crear_proveedor_con_alicuota_vacia(): void
    {
        $empresa = Empresa::query()->create([
            'razon_social' => 'Empresa Proveedores',
            'cuit' => '30111111111',
            'activa' => true,
        ]);
        $sucursal = Sucursal::query()->create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Casa central',
            'activa' => true,
        ]);

        foreach (['proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar'] as $perm) {
            Permission::findOrCreate($perm);
        }
        $role = Role::findOrCreate('Administrador');
        $role->givePermissionTo(['proveedores.ver', 'proveedores.crear', 'proveedores.editar', 'proveedores.eliminar']);

        $admin = User::factory()->create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'usuario' => 'admin-prov',
            'activo' => true,
        ]);
        $admin->assignRole($role);
        $this->actingAs($admin);

        $request = Request::create('/proveedores', 'POST', [
            'razon_social' => 'Proveedor Sin Alicuota',
            'cuit' => null,
            'condicion_iva' => 'RESPONSABLE_INSCRIPTO',
            'email' => null,
            'telefono' => null,
            'domicilio' => null,
            'localidad' => null,
            'alicuota_retencion_iibb' => null,
            'observaciones' => null,
            'activo' => '1',
        ]);
        $request->setUserResolver(fn () => $admin);

        $response = app(ProveedorController::class)->store($request);

        $this->assertTrue($response->isRedirect());
        $this->assertDatabaseHas('proveedores', [
            'razon_social' => 'Proveedor Sin Alicuota',
            'alicuota_retencion_iibb' => 0,
            'activo' => 1,
        ]);
        $this->assertSame(1, Proveedor::query()->count());
    }
}
