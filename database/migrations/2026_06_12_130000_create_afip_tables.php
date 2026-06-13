<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Emisores fiscales: cada CUIT con el que se puede facturar (multi-CUIT)
        Schema::create('emisores', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('razon_social');
            $table->string('cuit', 13);
            $table->string('condicion_iva', 30)->default('RESPONSABLE_INSCRIPTO')
                ->comment('RESPONSABLE_INSCRIPTO emite A/B, MONOTRIBUTO emite C');
            $table->string('ingresos_brutos', 30)->nullable();
            $table->date('inicio_actividades')->nullable();
            $table->string('domicilio')->nullable();
            $table->string('certificado_path')->nullable()->comment('Certificado X.509 de AFIP');
            $table->string('clave_privada_path')->nullable()->comment('Clave privada del certificado');
            $table->string('entorno', 15)->default('homologacion')->comment('homologacion, produccion');
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'cuit']);
        });

        Schema::create('puntos_venta', function (Blueprint $table) {
            $table->id();
            $table->foreignId('emisor_id')->constrained('emisores')->cascadeOnDelete();
            $table->unsignedSmallInteger('numero');
            $table->string('descripcion')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['emisor_id', 'numero']);
        });

        Schema::create('comprobantes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->foreignId('emisor_id')->constrained('emisores');
            $table->foreignId('punto_venta_id')->constrained('puntos_venta');
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedSmallInteger('tipo_comprobante')
                ->comment('Código AFIP: 1=FA 6=FB 11=FC 3=NCA 8=NCB 13=NCC');
            $table->unsignedBigInteger('numero')->nullable()->comment('Asignado al autorizar');
            $table->unsignedSmallInteger('doc_tipo')->default(99)->comment('80=CUIT 96=DNI 99=CF');
            $table->string('doc_numero', 20)->default('0');
            $table->string('receptor_nombre')->nullable();
            $table->string('receptor_condicion_iva', 30)->nullable();
            $table->decimal('neto', 12, 2)->default(0);
            $table->decimal('iva', 12, 2)->default(0);
            $table->decimal('exento', 12, 2)->default(0);
            $table->decimal('no_gravado', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->json('detalle_iva')->nullable()->comment('Desglose por alícuota');
            $table->string('cae', 20)->nullable();
            $table->date('cae_vencimiento')->nullable();
            $table->string('estado', 15)->default('pendiente')
                ->comment('pendiente, autorizado, rechazado, error');
            $table->text('mensaje_afip')->nullable();
            $table->json('respuesta_afip')->nullable();
            $table->date('fecha_emision');
            $table->timestamps();

            $table->index(['emisor_id', 'punto_venta_id', 'tipo_comprobante', 'numero'], 'comprobantes_numeracion_idx');
            $table->index('estado');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('comprobantes');
        Schema::dropIfExists('puntos_venta');
        Schema::dropIfExists('emisores');
    }
};
