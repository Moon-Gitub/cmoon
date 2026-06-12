<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('categorias', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });

        Schema::create('productos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('categoria_id')->nullable()->constrained('categorias')->nullOnDelete();
            $table->string('codigo', 50)->comment('Código de barras o interno');
            $table->string('nombre');
            $table->text('descripcion')->nullable();
            $table->string('unidad', 10)->default('UN');
            $table->boolean('pesable')->default(false)->comment('Producto de balanza (precio por kg)');
            $table->decimal('precio_compra', 12, 2)->default(0);
            $table->decimal('precio_venta', 12, 2)->default(0);
            $table->decimal('alicuota_iva', 5, 2)->default(21);
            $table->decimal('stock_minimo', 12, 3)->default(0);
            $table->string('imagen_path')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->softDeletes();

            $table->unique(['empresa_id', 'codigo']);
            $table->index('nombre');
        });

        Schema::create('stocks', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->decimal('cantidad', 12, 3)->default(0);
            $table->timestamps();

            $table->unique(['producto_id', 'sucursal_id']);
        });

        Schema::create('movimientos_stock', function (Blueprint $table) {
            $table->id();
            $table->foreignId('producto_id')->constrained('productos')->cascadeOnDelete();
            $table->foreignId('sucursal_id')->constrained('sucursales')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('tipo', 20)->comment('ajuste, venta, compra, transferencia');
            $table->decimal('cantidad', 12, 3)->comment('Positivo entra, negativo sale');
            $table->decimal('stock_resultante', 12, 3)->nullable();
            $table->string('observacion')->nullable();
            $table->nullableMorphs('referencia');
            $table->timestamps();

            $table->index(['producto_id', 'sucursal_id']);
        });

        Schema::create('listas_precio', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 100);
            $table->decimal('porcentaje', 6, 2)->default(0)
                ->comment('Ajuste sobre precio de venta: 10 = +10%, -5 = -5%');
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('listas_precio');
        Schema::dropIfExists('movimientos_stock');
        Schema::dropIfExists('stocks');
        Schema::dropIfExists('productos');
        Schema::dropIfExists('categorias');
    }
};
