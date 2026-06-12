<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'categorias.ver',
        'categorias.gestionar',
        'productos.ver',
        'productos.crear',
        'productos.editar',
        'productos.eliminar',
        'stock.ajustar',
        'listas-precio.ver',
        'listas-precio.gestionar',
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
            ->givePermissionTo(['productos.ver', 'categorias.ver']);

        Role::firstOrCreate(['name' => 'Cajero', 'guard_name' => 'web'])
            ->givePermissionTo(['productos.ver']);
    }

    public function down(): void
    {
        // Permisos base: no se revierten
    }
};
