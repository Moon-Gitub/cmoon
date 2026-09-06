<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'depositos.ver',
        'depositos.gestionar',
    ];

    public function up(): void
    {
        if (! Schema::hasTable('depositos')) {
            Schema::create('depositos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->string('nombre');
                $table->boolean('activo')->default(true);
                $table->timestamps();
                $table->index(['empresa_id', 'activo']);
            });
        }

        if (! Schema::hasTable('producto_lotes')) {
            Schema::create('producto_lotes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
                $table->foreignId('deposito_id')->nullable()->constrained('depositos')->nullOnDelete();
                $table->string('codigo_lote');
                $table->string('serie')->nullable();
                $table->date('vencimiento')->nullable();
                $table->decimal('cantidad', 12, 3)->default(0);
                $table->timestamps();
                $table->unique(['producto_id', 'codigo_lote', 'serie'], 'producto_lotes_producto_lote_serie_uq');
                $table->index(['empresa_id', 'producto_id']);
            });
        }

        // Stock existente queda en sucursales; no se migra a depósitos/lotes.

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'])
            ->givePermissionTo(self::PERMISOS);
    }

    public function down(): void
    {
        Schema::dropIfExists('producto_lotes');
        Schema::dropIfExists('depositos');

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::where('name', $permiso)->where('guard_name', 'web')->delete();
        }
    }
};
