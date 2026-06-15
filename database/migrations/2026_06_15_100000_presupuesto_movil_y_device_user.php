<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('presupuestos', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
            $table->string('origen', 10)->default('web')->after('estado')
                ->comment('web, movil');
        });

        DB::statement("ALTER TABLE presupuestos MODIFY estado VARCHAR(25) NOT NULL DEFAULT 'pendiente'");

        Schema::table('desktop_installations', function (Blueprint $table) {
            $table->foreignId('user_id')->nullable()->after('empresa_id')
                ->constrained('users')->nullOnDelete();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        Permission::firstOrCreate(['name' => 'presupuestos.crear_movil', 'guard_name' => 'web']);
        Permission::firstOrCreate(['name' => 'presupuestos.aprobar', 'guard_name' => 'web']);

        Role::findByName('Administrador', 'web')
            ?->givePermissionTo(['presupuestos.crear_movil', 'presupuestos.aprobar']);

        Role::findByName('Vendedor', 'web')
            ?->givePermissionTo('presupuestos.crear_movil');
    }

    public function down(): void
    {
        Schema::table('desktop_installations', function (Blueprint $table) {
            $table->dropConstrainedForeignId('user_id');
        });

        Schema::table('presupuestos', function (Blueprint $table) {
            $table->dropColumn(['uuid', 'origen']);
        });
    }
};
