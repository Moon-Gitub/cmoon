<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('compras', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales');
            $table->foreignId('proveedor_id')->constrained('proveedores');
            $table->foreignId('user_id')->constrained('users');
            $table->string('factura_numero', 30)->nullable()->comment('Comprobante del proveedor');
            $table->string('condicion', 20)->default('contado')->comment('contado, cuenta_corriente');
            $table->decimal('total', 12, 2);
            $table->string('estado', 15)->default('completada')->comment('completada, anulada');
            $table->text('observaciones')->nullable();
            $table->date('fecha');
            $table->timestamps();

            $table->index(['empresa_id', 'fecha']);
        });

        Schema::create('compra_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('compra_id')->constrained('compras')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('costo_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        Schema::create('presupuestos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
            $table->foreignId('user_id')->constrained('users');
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete()
                ->comment('Venta generada al convertir el presupuesto');
            $table->unsignedBigInteger('numero');
            $table->string('estado', 15)->default('pendiente')->comment('pendiente, convertido, anulado');
            $table->decimal('total', 12, 2);
            $table->date('valido_hasta')->nullable();
            $table->text('observaciones')->nullable();
            $table->date('fecha');
            $table->timestamps();

            $table->unique(['empresa_id', 'numero']);
        });

        Schema::create('presupuesto_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('presupuesto_id')->constrained('presupuestos')->cascadeOnDelete();
            $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
            $table->string('descripcion');
            $table->decimal('cantidad', 12, 3);
            $table->decimal('precio_unitario', 12, 2);
            $table->decimal('total', 12, 2);
            $table->timestamps();
        });

        // Combos: un producto compuesto descuenta stock de sus componentes
        Schema::create('combo_componentes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('combo_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('componente_id')->constrained('productos')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 3)->default(1);
            $table->timestamps();

            $table->unique(['combo_id', 'componente_id']);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->boolean('es_combo')->default(false)->after('pesable');
        });
    }

    public function down(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn('es_combo');
        });
        Schema::dropIfExists('combo_componentes');
        Schema::dropIfExists('presupuesto_items');
        Schema::dropIfExists('presupuestos');
        Schema::dropIfExists('compra_items');
        Schema::dropIfExists('compras');
    }
};
