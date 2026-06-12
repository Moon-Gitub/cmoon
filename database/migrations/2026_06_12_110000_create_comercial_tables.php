<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('tipo_documento', 10)->default('DNI')->comment('DNI, CUIT, CUIL, OTRO');
            $table->string('documento', 20)->nullable();
            $table->string('condicion_iva', 30)->default('CONSUMIDOR_FINAL');
            $table->string('email')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('domicilio')->nullable();
            $table->string('localidad')->nullable();
            $table->foreignId('lista_precio_id')->nullable()->constrained('listas_precio')->nullOnDelete();
            $table->decimal('limite_credito', 12, 2)->nullable()->comment('Tope de cta. cte., null = sin límite');
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'documento']);
            $table->index('nombre');
        });

        Schema::create('proveedores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('razon_social');
            $table->string('cuit', 13)->nullable();
            $table->string('condicion_iva', 30)->default('RESPONSABLE_INSCRIPTO');
            $table->string('email')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('domicilio')->nullable();
            $table->string('localidad')->nullable();
            $table->decimal('alicuota_retencion_iibb', 5, 2)->default(0)->comment('Para retenciones SIRCAR');
            $table->text('observaciones')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->index(['empresa_id', 'cuit']);
        });

        Schema::create('medios_pago', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->string('tipo', 30)->default('otro')
                ->comment('efectivo, tarjeta_debito, tarjeta_credito, transferencia, qr, cheque, cuenta_corriente, otro');
            $table->decimal('recargo_porcentaje', 5, 2)->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        // Cta. cte. unificada para clientes y proveedores (titular polimórfico).
        // Convención de signo: importe positivo aumenta la deuda del titular
        // (cliente nos debe más / nosotros debemos más al proveedor).
        Schema::create('movimientos_cuenta', function (Blueprint $table) {
            $table->id();
            $table->morphs('titular');
            $table->string('tipo', 20)->comment('factura, venta, pago, recibo, ajuste');
            $table->string('concepto');
            $table->decimal('importe', 12, 2);
            $table->nullableMorphs('referencia');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->date('fecha');
            $table->timestamps();

            $table->index(['titular_type', 'titular_id', 'fecha']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('movimientos_cuenta');
        Schema::dropIfExists('medios_pago');
        Schema::dropIfExists('proveedores');
        Schema::dropIfExists('clientes');
    }
};
