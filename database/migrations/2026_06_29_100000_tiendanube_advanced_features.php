<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Tabla de carritos abandonados / leads
        if (! Schema::hasTable('leads_abandonados')) {
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
        }

        // Campos adicionales en integraciones
        if (Schema::hasTable('tiendanube_integraciones')) {
            Schema::table('tiendanube_integraciones', function (Blueprint $table) {
                if (! Schema::hasColumn('tiendanube_integraciones', 'sync_prices')) {
                    $table->boolean('sync_prices')->default(false)->after('sync_orders');
                }
                if (! Schema::hasColumn('tiendanube_integraciones', 'sync_metafields')) {
                    $table->boolean('sync_metafields')->default(false)->after('sync_customers');
                }
                if (! Schema::hasColumn('tiendanube_integraciones', 'import_abandoned')) {
                    $table->boolean('import_abandoned')->default(false)->after('sync_metafields');
                }
                if (! Schema::hasColumn('tiendanube_integraciones', 'last_abandoned_sync_at')) {
                    $table->timestamp('last_abandoned_sync_at')->nullable()->after('last_order_sync_at');
                }
            });
        }

        // Campos para multi-ubicación en sucursales
        if (Schema::hasTable('sucursales') && ! Schema::hasColumn('sucursales', 'tn_location_id')) {
            Schema::table('sucursales', function (Blueprint $table) {
                $table->unsignedBigInteger('tn_location_id')->nullable()->after('activa');
            });
        }

        // Campos para tracking/despacho en ventas
        if (Schema::hasTable('ventas')) {
            if (! Schema::hasColumn('ventas', 'estado_envio')) {
                Schema::table('ventas', fn ($t) => $t->string('estado_envio')->nullable()->after('estado'));
            }
            if (! Schema::hasColumn('ventas', 'tracking_number')) {
                Schema::table('ventas', fn ($t) => $t->string('tracking_number')->nullable()->after('estado_envio'));
            }
            if (! Schema::hasColumn('ventas', 'tracking_url')) {
                Schema::table('ventas', fn ($t) => $t->string('tracking_url', 500)->nullable()->after('tracking_number'));
            }
            if (! Schema::hasColumn('ventas', 'despachada_at')) {
                Schema::table('ventas', fn ($t) => $t->timestamp('despachada_at')->nullable()->after('tracking_url'));
            }
            if (! Schema::hasColumn('ventas', 'entregada_at')) {
                Schema::table('ventas', fn ($t) => $t->timestamp('entregada_at')->nullable()->after('despachada_at'));
            }
        }

        // Campos promocionales en productos
        if (Schema::hasTable('productos')) {
            if (! Schema::hasColumn('productos', 'precio_promocional')) {
                Schema::table('productos', fn ($t) => $t->decimal('precio_promocional', 12, 2)->nullable()->after('precio_venta'));
            }
            if (! Schema::hasColumn('productos', 'promo_desde')) {
                Schema::table('productos', fn ($t) => $t->date('promo_desde')->nullable()->after('precio_promocional'));
            }
            if (! Schema::hasColumn('productos', 'promo_hasta')) {
                Schema::table('productos', fn ($t) => $t->date('promo_hasta')->nullable()->after('promo_desde'));
            }
            if (! Schema::hasColumn('productos', 'garantia_meses')) {
                Schema::table('productos', fn ($t) => $t->integer('garantia_meses')->nullable()->after('promo_hasta'));
            }
            if (! Schema::hasColumn('productos', 'codigo_interno')) {
                Schema::table('productos', fn ($t) => $t->string('codigo_interno')->nullable()->after('codigo'));
            }
            if (! Schema::hasColumn('productos', 'marca')) {
                Schema::table('productos', fn ($t) => $t->string('marca')->nullable()->after('nombre'));
            }
            if (! Schema::hasColumn('productos', 'origen')) {
                Schema::table('productos', fn ($t) => $t->string('origen')->nullable()->after('marca'));
            }
            if (! Schema::hasColumn('productos', 'peso')) {
                Schema::table('productos', fn ($t) => $t->decimal('peso', 8, 3)->nullable()->after('origen'));
            }
            if (! Schema::hasColumn('productos', 'alto')) {
                Schema::table('productos', fn ($t) => $t->decimal('alto', 8, 2)->nullable()->after('peso'));
            }
            if (! Schema::hasColumn('productos', 'ancho')) {
                Schema::table('productos', fn ($t) => $t->decimal('ancho', 8, 2)->nullable()->after('alto'));
            }
            if (! Schema::hasColumn('productos', 'largo')) {
                Schema::table('productos', fn ($t) => $t->decimal('largo', 8, 2)->nullable()->after('ancho'));
            }
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('leads_abandonados');

        $tnCols = ['sync_prices', 'sync_metafields', 'import_abandoned', 'last_abandoned_sync_at'];
        if (Schema::hasTable('tiendanube_integraciones')) {
            foreach ($tnCols as $col) {
                if (Schema::hasColumn('tiendanube_integraciones', $col)) {
                    Schema::table('tiendanube_integraciones', fn ($t) => $t->dropColumn($col));
                }
            }
        }

        if (Schema::hasTable('sucursales') && Schema::hasColumn('sucursales', 'tn_location_id')) {
            Schema::table('sucursales', fn ($t) => $t->dropColumn('tn_location_id'));
        }

        $ventasCols = ['estado_envio', 'tracking_number', 'tracking_url', 'despachada_at', 'entregada_at'];
        if (Schema::hasTable('ventas')) {
            foreach ($ventasCols as $col) {
                if (Schema::hasColumn('ventas', $col)) {
                    Schema::table('ventas', fn ($t) => $t->dropColumn($col));
                }
            }
        }

        $productosCols = ['precio_promocional', 'promo_desde', 'promo_hasta', 'garantia_meses', 'codigo_interno', 'marca', 'origen', 'peso', 'alto', 'ancho', 'largo'];
        if (Schema::hasTable('productos')) {
            foreach ($productosCols as $col) {
                if (Schema::hasColumn('productos', $col)) {
                    Schema::table('productos', fn ($t) => $t->dropColumn($col));
                }
            }
        }
    }
};
