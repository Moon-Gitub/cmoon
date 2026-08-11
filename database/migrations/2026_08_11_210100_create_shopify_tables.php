<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('shopify_integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('store_domain')->comment('ej. mi-tienda.myshopify.com');
            $table->text('access_token')->comment('Admin API access token (encrypted)');
            $table->string('api_key')->nullable();
            $table->text('api_secret')->nullable()->comment('encrypted — HMAC webhooks / OAuth');
            $table->string('webhook_secret')->nullable()->comment('si difiere del api_secret');
            $table->string('store_name')->nullable();
            $table->string('api_version', 20)->nullable();
            $table->json('scopes')->nullable();

            $table->boolean('sync_products')->default(true);
            $table->boolean('sync_orders')->default(true);
            $table->boolean('auto_create_products')->default(true);
            $table->boolean('push_products')->default(false);

            $table->foreignId('default_sucursal_id')->nullable()
                ->constrained('sucursales')->nullOnDelete();

            $table->timestamp('last_product_sync_at')->nullable();
            $table->timestamp('last_order_sync_at')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'store_domain']);
        });

        Schema::create('shopify_product_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('shopify_integraciones')->cascadeOnDelete();
            $table->foreignId('producto_id')
                ->constrained('productos')->cascadeOnDelete();
            $table->unsignedBigInteger('shopify_product_id');
            $table->unsignedBigInteger('shopify_variant_id')->nullable();
            $table->string('shopify_sku')->nullable();
            $table->timestamp('last_synced_at')->nullable();
            $table->timestamps();

            $table->unique(['integracion_id', 'producto_id']);
            $table->unique(
                ['integracion_id', 'shopify_product_id', 'shopify_variant_id'],
                'shopify_product_variant_unique'
            );
            $table->index('shopify_product_id');
        });

        Schema::create('shopify_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('shopify_integraciones')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('direccion', 10);
            $table->string('entidad_tipo', 30)->nullable();
            $table->unsignedBigInteger('entidad_id')->nullable();
            $table->json('request')->nullable();
            $table->json('response')->nullable();
            $table->string('status', 10)->default('ok');
            $table->text('mensaje')->nullable();
            $table->timestamps();

            $table->index(['integracion_id', 'created_at']);
            $table->index(['integracion_id', 'tipo']);
        });

        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'shopify_order_id')) {
                $table->unsignedBigInteger('shopify_order_id')->nullable()
                    ->comment('ID de orden Shopify si origen=shopify');
                $table->string('shopify_order_number')->nullable();
                $table->index('shopify_order_id');
            }
        });
    }

    public function down(): void
    {
        Schema::table('ventas', function (Blueprint $table) {
            if (Schema::hasColumn('ventas', 'shopify_order_id')) {
                $table->dropIndex(['shopify_order_id']);
                $table->dropColumn(['shopify_order_id', 'shopify_order_number']);
            }
        });

        Schema::dropIfExists('shopify_logs');
        Schema::dropIfExists('shopify_product_maps');
        Schema::dropIfExists('shopify_integraciones');
    }
};
