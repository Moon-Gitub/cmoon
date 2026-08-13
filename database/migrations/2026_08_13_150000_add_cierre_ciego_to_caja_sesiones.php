<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('caja_sesiones', function (Blueprint $table) {
            $table->json('detalle_sistema')->nullable()->after('monto_cierre_sistema');
            $table->json('detalle_declarado')->nullable()->after('detalle_sistema');
            $table->json('detalle_diferencias')->nullable()->after('detalle_declarado');
            $table->decimal('apertura_siguiente_monto', 12, 2)->nullable()->after('detalle_diferencias');
        });
    }

    public function down(): void
    {
        Schema::table('caja_sesiones', function (Blueprint $table) {
            $table->dropColumn([
                'detalle_sistema',
                'detalle_declarado',
                'detalle_diferencias',
                'apertura_siguiente_monto',
            ]);
        });
    }
};
