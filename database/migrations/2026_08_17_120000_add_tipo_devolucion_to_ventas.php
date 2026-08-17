<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->string('tipo', 20)->default('venta')->after('origen')
                ->comment('venta, devolucion (Devolución X, no fiscal)');
            $table->foreignId('venta_origen_id')->nullable()->after('tipo')
                ->constrained('ventas')->nullOnDelete();
            $table->unsignedBigInteger('venta_origen_numero')->nullable()->after('venta_origen_id');
            $table->index(['empresa_id', 'tipo']);
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['empresa_id', 'tipo']);
            $table->dropConstrainedForeignId('venta_origen_id');
            $table->dropColumn(['tipo', 'venta_origen_numero']);
        });
    }
};
