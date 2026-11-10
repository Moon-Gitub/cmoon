<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->string('tipo_documento', 30)->nullable()->after('razon_social');
            $table->string('documento', 32)->nullable()->after('tipo_documento');
            $table->string('condicion_iva', 40)->nullable()->after('cuit');
            $table->string('direccion', 255)->nullable()->after('telefono');
            $table->boolean('legacy_bloqueado')->default(false)->after('modo_cobro');
        });

        Schema::create('billing_legacy_detalles', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_movimiento_id')->unique();
            $table->foreignId('customer_id')->constrained('billing_customers')->cascadeOnDelete();
            $table->string('metodo_pago', 80)->nullable();
            $table->string('numero_recibo', 40)->nullable();
            $table->string('referencia_mp', 80)->nullable();
            $table->string('origen', 40)->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_legacy_detalles');
        Schema::table('billing_customers', function (Blueprint $table) {
            $table->dropColumn(['tipo_documento', 'documento', 'condicion_iva', 'direccion', 'legacy_bloqueado']);
        });
    }
};
