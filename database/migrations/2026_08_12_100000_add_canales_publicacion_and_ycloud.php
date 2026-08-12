<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('productos', function (Blueprint $table) {
            if (! Schema::hasColumn('productos', 'publicar_shopify')) {
                $table->boolean('publicar_shopify')->default(false)->after('activo');
            }
            if (! Schema::hasColumn('productos', 'publicar_whatsapp')) {
                $table->boolean('publicar_whatsapp')->default(false)->after('publicar_shopify');
            }
            if (! Schema::hasColumn('productos', 'publicar_tiendanube')) {
                $table->boolean('publicar_tiendanube')->default(false)->after('publicar_whatsapp');
            }
        });

        Schema::create('ycloud_integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->text('api_key')->comment('YCloud X-API-Key (encrypted)');
            $table->string('phone_from', 32)->comment('Número Business E.164, ej. +54911...');
            $table->string('waba_id')->nullable();
            $table->string('webhook_secret')->nullable();
            $table->string('catalog_template')->nullable()->comment('Nombre de plantilla catálogo Meta');
            $table->boolean('bot_activo')->default(true);
            $table->boolean('auto_reply')->default(true);
            $table->boolean('activo')->default(true);
            $table->timestamp('last_message_at')->nullable();
            $table->timestamps();

            $table->unique('empresa_id');
            $table->index('phone_from');
        });

        Schema::create('ycloud_conversaciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('ycloud_integraciones')->cascadeOnDelete();
            $table->string('telefono', 32);
            $table->string('nombre')->nullable();
            $table->timestamp('handoff_until')->nullable();
            $table->timestamp('last_inbound_at')->nullable();
            $table->timestamps();

            $table->unique(['integracion_id', 'telefono']);
        });

        Schema::create('ycloud_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')
                ->constrained('ycloud_integraciones')->cascadeOnDelete();
            $table->foreignId('conversacion_id')->nullable()
                ->constrained('ycloud_conversaciones')->nullOnDelete();
            $table->string('direccion', 10);
            $table->string('from_phone', 32)->nullable();
            $table->string('to_phone', 32)->nullable();
            $table->string('wamid')->nullable();
            $table->text('body')->nullable();
            $table->text('respuesta')->nullable();
            $table->json('producto_ids')->nullable();
            $table->boolean('handoff')->default(false);
            $table->string('status', 16)->default('ok');
            $table->json('raw')->nullable();
            $table->timestamps();

            $table->index(['integracion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('ycloud_mensajes');
        Schema::dropIfExists('ycloud_conversaciones');
        Schema::dropIfExists('ycloud_integraciones');

        Schema::table('productos', function (Blueprint $table) {
            foreach (['publicar_shopify', 'publicar_whatsapp', 'publicar_tiendanube'] as $col) {
                if (Schema::hasColumn('productos', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
