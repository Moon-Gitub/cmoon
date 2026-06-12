<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cajas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['sucursal_id', 'nombre']);
        });

        Schema::create('caja_sesiones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_id')->constrained('cajas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->decimal('monto_apertura', 12, 2)->default(0);
            $table->decimal('monto_cierre_declarado', 12, 2)->nullable();
            $table->decimal('monto_cierre_sistema', 12, 2)->nullable();
            $table->string('estado', 10)->default('abierta')->comment('abierta, cerrada');
            $table->text('observaciones')->nullable();
            $table->timestamp('abierta_at');
            $table->timestamp('cerrada_at')->nullable();
            $table->timestamps();

            $table->index(['caja_id', 'estado']);
        });

        Schema::create('caja_movimientos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('caja_sesion_id')->constrained('caja_sesiones')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 10)->comment('ingreso, egreso');
            $table->string('concepto');
            $table->decimal('importe', 12, 2);
            $table->timestamps();
        });

        Schema::create('ventas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique()->comment('Idempotencia para sincronización offline');
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('caja_sesion_id')->nullable()->constrained('caja_sesiones')->nullOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->unsignedBigInteger('numero')->comment('Correlativo interno por empresa');
            $table->string('estado', 15)->default('completada')->comment('completada, anulada');
            $table->string('origen', 10)->default('pos')->comment('pos, offline');
            $table->decimal('subtotal', 12, 2);
            $table->decimal('descuento', 12, 2)->default(0);
            $table->decimal('recargo', 12, 2)->default(0);
            $table->decimal('total', 12, 2);
            $table->string('motivo_anulacion')->nullable();
            $table->timestamp('anulada_at')->nullable();
            $table->foreignId('anulada_por')->nullable()->constrained('users')->nullOnDelete();
            $table->timestamp('fecha');
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
            $table->index(['empresa_id', 'fecha']);
        });

        Schema::create('venta_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('alicuota_iva', 5, 2)->default(21);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('venta_pagos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('venta_id')->constrained('ventas')->cascadeOnDelete();
            $table->foreignId('medio_pago_id')->constrained('medios_pago');
            $table->decimal('importe', 12, 2);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('venta_pagos');
        Schema::dropIfExists('venta_items');
        Schema::dropIfExists('ventas');
        Schema::dropIfExists('caja_movimientos');
        Schema::dropIfExists('caja_sesiones');
        Schema::dropIfExists('cajas');
    }
};
