<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->index(['empresa_id', 'nombre'], 'productos_empresa_nombre_idx');
            $table->index(['empresa_id', 'activo', 'codigo'], 'productos_empresa_activo_codigo_idx');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->index(['empresa_id', 'razon_social'], 'proveedores_empresa_razon_idx');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->index(['empresa_id', 'email'], 'clientes_empresa_email_idx');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropIndex('productos_empresa_nombre_idx');
            $table->dropIndex('productos_empresa_activo_codigo_idx');
        });

        Schema::table('proveedores', function (Blueprint $table) {
            $table->dropIndex('proveedores_empresa_razon_idx');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropIndex('clientes_empresa_email_idx');
        });
    }
};
