<?php

use Illuminate\Database\Migrations\Migration;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'informes.ver', 'guard_name' => 'web']);

        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'])
            ->givePermissionTo('informes.ver');
    }

    public function down(): void
    {
        // Permisos base: no se revierten
    }
};
