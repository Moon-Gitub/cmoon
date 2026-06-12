<?php

use App\Models\Empresa;
use App\Models\MedioPago;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'clientes.ver',
        'clientes.crear',
        'clientes.editar',
        'clientes.eliminar',
        'proveedores.ver',
        'proveedores.crear',
        'proveedores.editar',
        'proveedores.eliminar',
        'cuentas.ver',
        'cuentas.registrar',
        'medios-pago.ver',
        'medios-pago.gestionar',
    ];

    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'])
            ->givePermissionTo(self::PERMISOS);

        Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web'])
            ->givePermissionTo(['clientes.ver', 'clientes.crear', 'cuentas.ver']);

        $empresaId = Empresa::value('id');

        if ($empresaId) {
            $medios = [
                ['nombre' => 'Efectivo', 'tipo' => 'efectivo'],
                ['nombre' => 'Tarjeta de débito', 'tipo' => 'tarjeta_debito'],
                ['nombre' => 'Tarjeta de crédito', 'tipo' => 'tarjeta_credito'],
                ['nombre' => 'Transferencia', 'tipo' => 'transferencia'],
                ['nombre' => 'QR / Billetera virtual', 'tipo' => 'qr'],
                ['nombre' => 'Cuenta corriente', 'tipo' => 'cuenta_corriente'],
            ];

            foreach ($medios as $medio) {
                MedioPago::firstOrCreate(
                    ['empresa_id' => $empresaId, 'nombre' => $medio['nombre']],
                    ['tipo' => $medio['tipo'], 'activo' => true]
                );
            }
        }
    }

    public function down(): void
    {
        // Datos base: no se revierten
    }
};
