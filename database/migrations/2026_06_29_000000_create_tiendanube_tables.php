<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tiendanube_integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedBigInteger('store_id')->comment('ID de tienda en Tiendanube');
            $table->text('access_token')->comment('Token encriptado');
            $table->string('store_name')->nullable();
            $table->string('store_url')->nullable();
            $table->json('scopes')->nullable();

            // Configuración de sincronización
            $table->boolean('sync_products')->default(true)->comment('Exportar productos a TN');
            $table->boolean('sync_stock')->default(true)->comment('Sincronizar stock automáticamente');
            $table->boolean('sync_orders')->default(true)->comment('Importar órdenes como ventas');
            $table->boolean('sync_customers')->default(false)->comment('Sincronizar clientes');
            $table->boolean('auto_create_products')->default(false)->comment('Crear productos al importar de TN');

            $table->foreignId('default_sucursal_id')->nullable()
                ->constrained('sucursales')->nullOnDelete()
                ->comment('Sucursal para stock y ventas de TN');

            $table->string('webhook_secret', 64)->nullable();
            $table->timestamp('last_product_sync_at')->nullable();
            $table->timestamp('last_stock_sync_at')->nullable();
            $table->timestamp('last_order_sync_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'store_id']);
        });

        Schema::create('tiendanube_product_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('tiendanube_integraciones')->cascadeOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')->cascadeOnDelete();
            $table->unsignedBigInteger('tn_product_id');
            $table->unsignedBigInteger('tn_variant_id')->nullable()
                ->comment('ID de variante si el producto tiene variantes');
            $table->string('tn_sku')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['integracion_id', 'producto_id']);
            $table->unique(['integracion_id', 'tn_product_id', 'tn_variant_id'], 'tn_product_variant_unique');
            $table->index('tn_product_id');
        });

        Schema::create('tiendanube_category_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('tiendanube_integraciones')->cascadeOnDelete();
            $table->foreignId('categoria_id')
                ->constrained('categorias')->cascadeOnDelete();
            $table->unsignedBigInteger('tn_category_id');
            $table->timestamps();

            $table->unique(['integracion_id', 'categoria_id']);
            $table->unique(['integracion_id', 'tn_category_id']);
        });

        Schema::create('tiendanube_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('tiendanube_integraciones')->cascadeOnDelete();
            $table->string('tipo', 30)->comment('product_sync, stock_sync, order_import, webhook, auth, error');
            $table->string('direccion', 10)->comment('push, pull, webhook');
            $table->string('entidad_tipo', 30)->nullable()->comment('product, order, stock, category');
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('status', 10)->default('ok')->comment('ok, error, pending');
            $table->text('mensaje')->nullable();
            $table->timestamps();

            $table->index(['integracion_id', 'created_at']);
            $table->index(['integracion_id', 'tipo']);
        });

        // Campo para vincular ventas con órdenes de Tiendanube
        Schema::table('ventas', function (Blueprint $table) {
            $table->unsignedBigInteger('tn_order_id')->nullable()->after('origen')
                ->comment('ID de orden en Tiendanube si origen=tiendanube');
            $table->string('tn_order_number')->nullable()->after('tn_order_id');

            $table->index('tn_order_id');
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            $table->dropIndex(['tn_order_id']);
            $table->dropColumn(['tn_order_id', 'tn_order_number']);
        });

        Schema::dropIfExists('tiendanube_logs');
        Schema::dropIfExists('tiendanube_category_maps');
        Schema::dropIfExists('tiendanube_product_maps');
        Schema::dropIfExists('tiendanube_integraciones');
    }
};
