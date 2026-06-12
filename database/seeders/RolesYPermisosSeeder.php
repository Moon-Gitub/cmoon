<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

class RolesYPermisosSeeder extends Seeder
{
    /**
     * Permisos por módulo. Se amplía en cada fase de la migración.
     */
    private const PERMISOS = [
        'usuarios.ver',
        'usuarios.crear',
        'usuarios.editar',
        'usuarios.eliminar',
        'roles.gestionar',
        'empresa.ver',
        'empresa.editar',
        'sucursales.ver',
        'sucursales.gestionar',
    ];

    public function run(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $admin = Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web']);
        $admin->syncPermissions(Permission::all());

        Role::firstOrCreate(['name' => 'Vendedor', 'guard_name' => 'web']);
        Role::firstOrCreate(['name' => 'Cajero', 'guard_name' => 'web']);
    }
}
