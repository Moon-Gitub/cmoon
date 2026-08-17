<?php

namespace Tests\Feature;

use App\Models\Caja;
use App\Models\CajaSesion;
use App\Models\Cliente;
use App\Models\Empresa;
use App\Models\MedioPago;
use App\Models\MovimientoCuenta;
use App\Models\MovimientoStock;
use App\Models\Producto;
use App\Models\Stock;
use App\Models\Sucursal;
use App\Models\User;
use App\Services\VentaService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DevolucionXTest extends TestCase
{
    use RefreshDatabase;

    public function test_devolucion_x_suma_stock_y_resta_en_caja(): void
    {
        [$user, $sucursal, $producto, $efectivo, $sesion] = $this->escenario();
        $this->actingAs($user);

        $servicio = app(VentaService::class);

        $venta = $servicio->crear([
            'uuid' => (string) Str::uuid(),
            'sucursal_id' => $sucursal->id,
            'caja_sesion_id' => $sesion->id,
            'items' => [[
                'producto_id' => $producto->id,
                'descripcion' => $producto->nombre,
                'cantidad' => 2,
                'precio_unitario' => 100,
                'alicuota_iva' => 21,
            ]],
            'pagos' => [['medio_pago_id' => $efectivo->id, 'importe' => 200]],
        ], $user->id);

        $this->assertSame('venta', $venta->tipo);
        $this->assertEquals(-2.0, (float) Stock::where('producto_id', $producto->id)->value('cantidad'));

        $devolucion = $servicio->crear([
            'uuid' => (string) Str::uuid(),
            'sucursal_id' => $sucursal->id,
            'caja_sesion_id' => $sesion->id,
            'tipo' => 'devolucion',
            'venta_origen_numero' => $venta->numero,
            'items' => [[
                'producto_id' => $producto->id,
                'descripcion' => $producto->nombre,
                'cantidad' => 1,
                'precio_unitario' => 100,
                'alicuota_iva' => 21,
            ]],
            'pagos' => [['medio_pago_id' => $efectivo->id, 'importe' => 100]],
        ], $user->id);

        $this->assertTrue($devolucion->esDevolucion());
        $this->assertSame($venta->id, $devolucion->venta_origen_id);
        $this->assertEquals(-1.0, (float) Stock::where('producto_id', $producto->id)->value('cantidad'));
        $this->assertEquals(-100.0, $devolucion->totalConSigno());
        $this->assertTrue(
            MovimientoStock::where('referencia_id', $devolucion->id)->where('tipo', 'devolucion')->exists()
        );

        $sesion->load('caja.sucursal', 'usuario');
        $this->assertEquals(150.0, $sesion->efectivoEsperado());
    }

    public function test_devolucion_en_cuenta_corriente_acredita_al_cliente(): void
    {
        [$user, $sucursal, $producto] = $this->escenario();
        $this->actingAs($user);

        $cliente = Cliente::query()->create([
            'empresa_id' => $user->empresa_id,
            'nombre' => 'Cliente CC',
            'activo' => true,
        ]);
        $ctaCte = MedioPago::query()->create([
            'empresa_id' => $user->empresa_id,
            'nombre' => 'Cuenta corriente',
            'tipo' => 'cuenta_corriente',
            'activo' => true,
        ]);

        $devolucion = app(VentaService::class)->crear([
            'uuid' => (string) Str::uuid(),
            'sucursal_id' => $sucursal->id,
            'cliente_id' => $cliente->id,
            'tipo' => 'devolucion',
            'items' => [[
                'producto_id' => $producto->id,
                'descripcion' => $producto->nombre,
                'cantidad' => 1,
                'precio_unitario' => 50,
            ]],
            'pagos' => [['medio_pago_id' => $ctaCte->id, 'importe' => 50]],
        ], $user->id);

        $mov = MovimientoCuenta::query()
            ->where('referencia_id', $devolucion->id)
            ->where('tipo', 'devolucion')
            ->first();

        $this->assertNotNull($mov);
        $this->assertEquals(-50.0, (float) $mov->importe);
        $this->assertEquals(-50.0, $cliente->fresh()->saldoCuenta());
    }

    public function test_anular_devolucion_vuelve_a_descontar_stock(): void
    {
        [$user, $sucursal, $producto, $efectivo] = $this->escenario();
        $this->actingAs($user);

        $servicio = app(VentaService::class);
        $devolucion = $servicio->crear([
            'uuid' => (string) Str::uuid(),
            'sucursal_id' => $sucursal->id,
            'tipo' => 'devolucion',
            'items' => [[
                'producto_id' => $producto->id,
                'descripcion' => $producto->nombre,
                'cantidad' => 3,
                'precio_unitario' => 10,
            ]],
            'pagos' => [['medio_pago_id' => $efectivo->id, 'importe' => 30]],
        ], $user->id);

        $this->assertEquals(3.0, (float) Stock::where('producto_id', $producto->id)->value('cantidad'));

        $servicio->anular($devolucion, 'error de carga', $user->id);

        $this->assertSame('anulada', $devolucion->fresh()->estado);
        $this->assertEquals(0.0, (float) Stock::where('producto_id', $producto->id)->value('cantidad'));
    }

    /**
     * @return array{0: User, 1: Sucursal, 2: Producto, 3: MedioPago, 4: CajaSesion}
     */
    private function escenario(): array
    {
        $empresa = Empresa::query()->create([
            'razon_social' => 'Empresa Dev X',
            'cuit' => '30999888777',
            'activa' => true,
        ]);
        $sucursal = Sucursal::query()->create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Casa central',
            'activa' => true,
        ]);
        $user = User::factory()->create([
            'empresa_id' => $empresa->id,
            'sucursal_id' => $sucursal->id,
            'usuario' => 'cajero-devx',
            'activo' => true,
        ]);
        $producto = Producto::query()->create([
            'empresa_id' => $empresa->id,
            'codigo' => 'DEV-1',
            'nombre' => 'Producto devolución',
            'precio_venta' => 100,
            'precio_compra' => 40,
            'unidad' => 'UN',
            'activo' => true,
        ]);
        $efectivo = MedioPago::query()->create([
            'empresa_id' => $empresa->id,
            'nombre' => 'Efectivo',
            'tipo' => 'efectivo',
            'activo' => true,
        ]);
        $caja = Caja::query()->create([
            'sucursal_id' => $sucursal->id,
            'nombre' => 'Caja 1',
            'activa' => true,
        ]);
        $sesion = CajaSesion::query()->create([
            'caja_id' => $caja->id,
            'user_id' => $user->id,
            'monto_apertura' => 50,
            'estado' => 'abierta',
            'abierta_at' => now(),
        ]);

        return [$user, $sucursal, $producto, $efectivo, $sesion];
    }
}
