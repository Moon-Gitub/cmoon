<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->boolean('agente_retencion_iibb')->default(false)->after('activa');
            $table->unsignedSmallInteger('codigo_jurisdiccion_iibb')->default(913)->after('agente_retencion_iibb')
                ->comment('Código CM SIRCAR (913 = Mendoza)');
            $table->unsignedSmallInteger('tipo_regimen_retencion_default')->default(101)->after('codigo_jurisdiccion_iibb');
            $table->unsignedInteger('proximo_numero_recibo')->default(1)->after('tipo_regimen_retencion_default');
        });

        Schema::table('movimientos_cuenta', function (Blueprint $table) {
            $table->string('factura_numero', 30)->nullable()->after('fecha');
            $table->decimal('factura_neto', 12, 2)->nullable()->after('factura_numero');
            $table->decimal('factura_iva', 12, 2)->nullable()->after('factura_neto');
            $table->foreignId('medio_pago_id')->nullable()->after('factura_iva')
                ->constrained('medios_pago')->nullOnDelete();
            $table->foreignId('caja_sesion_id')->nullable()->after('medio_pago_id')
                ->constrained('caja_sesiones')->nullOnDelete();
        });

        Schema::table('retenciones', function (Blueprint $table) {
            $table->unsignedInteger('numero_recibo')->nullable()->after('proveedor_id');
            $table->foreignId('movimiento_cuenta_id')->nullable()->after('user_id')
                ->constrained('movimientos_cuenta')->nullOnDelete();
            $table->decimal('monto_neto_pagado', 12, 2)->nullable()->after('monto')
                ->comment('Efectivo neto pagado al proveedor (egreso caja)');
        });
    }

    public function down(): void
    {
        Schema::table('retenciones', function (Blueprint $table) {
            $table->dropConstrainedForeignId('movimiento_cuenta_id');
            $table->dropColumn(['numero_recibo', 'monto_neto_pagado']);
        });

        Schema::table('movimientos_cuenta', function (Blueprint $table) {
            $table->dropConstrainedForeignId('caja_sesion_id');
            $table->dropConstrainedForeignId('medio_pago_id');
            $table->dropColumn(['factura_numero', 'factura_neto', 'factura_iva']);
        });

        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'agente_retencion_iibb',
                'codigo_jurisdiccion_iibb',
                'tipo_regimen_retencion_default',
                'proximo_numero_recibo',
            ]);
        });
    }
};
