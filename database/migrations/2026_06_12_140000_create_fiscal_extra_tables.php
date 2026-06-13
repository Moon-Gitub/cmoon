<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('comprobantes', function (Blueprint $table) {
            // Notas de crédito/débito referencian al comprobante original
            $table->foreignId('comprobante_asociado_id')->nullable()
                ->after('venta_id')->constrained('comprobantes')->nullOnDelete();
            // Ítems libres para facturas manuales (sin venta asociada)
            $table->json('detalle_items')->nullable()->after('detalle_iva');
            $table->string('concepto')->nullable()->after('detalle_items')
                ->comment('Motivo en notas de crédito/débito');
        });

        // Retenciones IIBB practicadas a proveedores (SIRCAR)
        Schema::create('retenciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('factura_numero', 30)->comment('Factura del proveedor que origina la retención');
            $table->decimal('factura_neto', 12, 2)->comment('Base imponible');
            $table->decimal('alicuota', 6, 3)->comment('Porcentaje, ej 1.25');
            $table->decimal('monto', 12, 2);
            $table->date('fecha');
            $table->unsignedSmallInteger('regimen')->default(101);
            $table->unsignedSmallInteger('jurisdiccion')->default(913)->comment('913 = Mendoza');
            $table->boolean('anulada')->default(false);
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('retenciones');
        Schema::table('comprobantes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('comprobante_asociado_id');
        });
    }
};
