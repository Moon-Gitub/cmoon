<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de carritos abandonados / leads
        Schema::create('leads_abandonados', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained()->cascadeOnDelete();
            $table->foreignId('cliente_id')->nullable()->constrained()->nullOnDelete();
            $table->unsignedBigInteger('tn_checkout_id')->nullable()->unique();
            $table->string('email');
            $table->string('telefono')->nullable();
            $table->decimal('total_carrito', 12, 2)->default(0);
            $table->json('productos_json')->nullable();
            $table->string('checkout_url', 500)->nullable();
            $table->timestamp('abandonado_at')->nullable();
            $table->boolean('contactado')->default(false);
            $table->timestamp('contactado_at')->nullable();
            $table->string('resultado_contacto')->nullable();
            $table->text('notas')->nullable();
            $table->timestamps();

            $table->index(['empresa_id', 'contactado']);
            $table->index(['empresa_id', 'abandonado_at']);
        });

        // Campos adicionales en integraciones
        Schema::table('tiendanube_integraciones', function (Blueprint $table) {
            $table->boolean('sync_prices')->default(false)->after('sync_orders');
            $table->boolean('sync_customers')->default(false)->after('sync_prices');
            $table->boolean('sync_metafields')->default(false)->after('sync_customers');
            $table->boolean('import_abandoned')->default(false)->after('sync_metafields');
            $table->timestamp('last_abandoned_sync_at')->nullable()->after('last_order_sync_at');
        });

        // Campos para multi-ubicación en sucursales
        Schema::table('sucursales', function (Blueprint $table) {
            if (! Schema::hasColumn('sucursales', 'tn_location_id')) {
                $table->unsignedBigInteger('tn_location_id')->nullable()->after('activa');
            }
        });

        // Campos para tracking/despacho en ventas
        Schema::table('ventas', function (Blueprint $table) {
            if (! Schema::hasColumn('ventas', 'estado_envio')) {
                $table->string('estado_envio')->nullable()->after('estado');
            }
            if (! Schema::hasColumn('ventas', 'tracking_number')) {
                $table->string('tracking_number')->nullable()->after('estado_envio');
            }
            if (! Schema::hasColumn('ventas', 'tracking_url')) {
                $table->string('tracking_url', 500)->nullable()->after('tracking_number');
            }
            if (! Schema::hasColumn('ventas', 'despachada_at')) {
                $table->timestamp('despachada_at')->nullable()->after('tracking_url');
            }
            if (! Schema::hasColumn('ventas', 'entregada_at')) {
                $table->timestamp('entregada_at')->nullable()->after('despachada_at');
            }
        });

        // Campos promocionales en productos
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'precio_promocional')) {
                $table->decimal('precio_promocional', 12, 2)->nullable()->after('precio_venta');
            }
            if (! Schema::hasColumn('productos', 'promo_desde')) {
                $table->date('promo_desde')->nullable()->after('precio_promocional');
            }
            if (! Schema::hasColumn('productos', 'promo_hasta')) {
                $table->date('promo_hasta')->nullable()->after('promo_desde');
            }
            if (! Schema::hasColumn('productos', 'garantia_meses')) {
                $table->integer('garantia_meses')->nullable()->after('promo_hasta');
            }
            if (! Schema::hasColumn('productos', 'codigo_interno')) {
                $table->string('codigo_interno')->nullable()->after('codigo');
            }
            if (! Schema::hasColumn('productos', 'marca')) {
                $table->string('marca')->nullable()->after('nombre');
            }
            if (! Schema::hasColumn('productos', 'origen')) {
                $table->string('origen')->nullable()->after('marca');
            }
            if (! Schema::hasColumn('productos', 'peso')) {
                $table->decimal('peso', 8, 3)->nullable()->after('origen');
            }
            if (! Schema::hasColumn('productos', 'alto')) {
                $table->decimal('alto', 8, 2)->nullable()->after('peso');
            }
            if (! Schema::hasColumn('productos', 'ancho')) {
                $table->decimal('ancho', 8, 2)->nullable()->after('alto');
            }
            if (! Schema::hasColumn('productos', 'largo')) {
                $table->decimal('largo', 8, 2)->nullable()->after('ancho');
            }
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('leads_abandonados');

        Schema::table('tiendanube_integraciones', function (Blueprint $table) {
            $table->dropColumn([
                'sync_prices',
                'sync_customers',
                'sync_metafields',
                'import_abandoned',
                'last_abandoned_sync_at',
            ]);
        });

        Schema::table('sucursales', function (Blueprint $table) {
            $table->dropColumn('tn_location_id');
        });

        Schema::table('ventas', function (Blueprint $table) {
            $table->dropColumn([
                'estado_envio',
                'tracking_number',
                'tracking_url',
                'despachada_at',
                'entregada_at',
            ]);
        });

        Schema::table('productos', function (Blueprint $table) {
            $table->dropColumn([
                'precio_promocional',
                'promo_desde',
                'promo_hasta',
                'garantia_meses',
                'codigo_interno',
                'marca',
                'origen',
                'peso',
                'alto',
                'ancho',
                'largo',
            ]);
        });
    }
};
