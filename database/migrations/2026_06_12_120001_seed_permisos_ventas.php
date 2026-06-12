<?php

use App\Models\Caja;
use App\Models\Sucursal;
use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'pos.vender',
        'ventas.ver',
        'ventas.anular',
        'cajas.ver',
        'cajas.operar',
        'cajas.gestionar',
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
            ->givePermissionTo(['pos.vender', 'ventas.ver']);

        Role::firstOrCreate(['name' => 'Cajero', 'guard_name' => 'web'])
            ->givePermissionTo(['pos.vender', 'ventas.ver', 'cajas.ver', 'cajas.operar']);

        // Una caja principal por sucursal existente
        foreach (Sucursal::all() as $sucursal) {
            Caja::firstOrCreate(
                ['sucursal_id' => $sucursal->id, 'nombre' => 'Caja principal'],
                ['activa' => true]
            );
        }
    }

    public function down(): void
    {
        // Permisos base: no se revierten
    }
};
