<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('usuario', 50)->unique()->nullable()->after('name');
            $table->foreignId('empresa_id')->nullable()->after('password')
                ->constrained('empresas')->nullOnDelete();
            $table->foreignId('sucursal_id')->nullable()->after('empresa_id')
                ->constrained('sucursales')->nullOnDelete();
            $table->boolean('activo')->default(true)->after('sucursal_id');
            $table->string('foto_path')->nullable()->after('activo');
            $table->timestamp('ultimo_acceso_at')->nullable()->after('foto_path');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('empresa_id');
            $table->dropConstrainedForeignId('sucursal_id');
            $table->dropColumn(['usuario', 'activo', 'foto_path', 'ultimo_acceso_at']);
        });
    }
};
