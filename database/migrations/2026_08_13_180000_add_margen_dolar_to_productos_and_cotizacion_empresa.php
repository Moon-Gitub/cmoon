<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->decimal('precio_compra_dolar', 12, 2)->default(0)->after('precio_compra');
            $table->decimal('margen_ganancia', 8, 2)->default(0)->after('precio_compra_dolar')
                ->comment('Margen % sobre compra para calcular venta neta');
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->decimal('cotizacion_dolar', 12, 2)->default(0)->after('color_primario')
                ->comment('Cotización U$S → $ para carga de productos');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn(['precio_compra_dolar', 'margen_ganancia']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn('cotizacion_dolar');
        });
    }
};
