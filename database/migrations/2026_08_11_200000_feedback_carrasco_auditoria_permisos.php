<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;

return new class extends Migration
{
    private const PERMISOS = [
        'ventas.editar_fecha',
        'productos.precio_masivo',
        'productos.auditoria',
    ];

    public function up(): void
    {
        Schema::create('producto_auditoria', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('campo', 50);
            $table->text('valor_anterior')->nullable();
            $table->text('valor_nuevo')->nullable();
            $table->string('origen', 40)->default('update');
            $table->timestamp('created_at')->useCurrent();
            $table->index(['producto_id', 'created_at']);
        });

        foreach (self::PERMISOS as $permiso) {
            Permission::findOrCreate($permiso);
        }

        Role::where('name', 'admin')->first()?->givePermissionTo(self::PERMISOS);
        Role::where('name', 'gerente')->first()?->givePermissionTo(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_auditoria');

        foreach (self::PERMISOS as $permiso) {
            Permission::findByName($permiso)?->delete();
        }
    }
};
