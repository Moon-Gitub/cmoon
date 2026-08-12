<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'ia_plan')) {
                $table->string('ia_plan', 20)->default('incluido')->after('proximo_numero_recibo');
                $table->date('ia_abono_hasta')->nullable()->after('ia_plan');
                $table->unsignedInteger('ia_cupo_override')->nullable()->after('ia_abono_hasta');
                $table->timestamp('ia_abono_solicitado_at')->nullable()->after('ia_cupo_override');
            }
        });

        Schema::create('ia_uso_mensual', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->char('periodo', 7);
            $table->unsignedInteger('usados')->default(0);
            $table->timestamps();
            $table->unique(['empresa_id', 'periodo']);
        });

        Schema::create('ia_mensajes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('origen', 20);
            $table->string('rol', 16);
            $table->text('body');
            $table->boolean('cuenta_cupo')->default(true);
            $table->timestamps();
            $table->index(['empresa_id', 'created_at']);
        });

        Schema::create('n8n_integraciones', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('base_url')->nullable();
            $table->string('header_name')->nullable()->default('X-N8N-Auth');
            $table->text('header_value')->nullable()->comment('encrypted — Header Auth de n8n');
            $table->string('inbound_secret')->nullable();
            $table->json('webhooks')->nullable();
            $table->boolean('activo')->default(true);
            $table->timestamps();
            $table->unique('empresa_id');
        });

        Schema::create('n8n_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('integracion_id')->constrained('n8n_integraciones')->cascadeOnDelete();
            $table->string('evento', 64);
            $table->string('direccion', 8);
            $table->string('url')->nullable();
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->json('payload')->nullable();
            $table->string('status', 16)->default('ok');
            $table->text('mensaje')->nullable();
            $table->timestamps();
            $table->index(['integracion_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('n8n_logs');
        Schema::dropIfExists('n8n_integraciones');
        Schema::dropIfExists('ia_mensajes');
        Schema::dropIfExists('ia_uso_mensual');

        Schema::table('empresas', function (Blueprint $table) {
            foreach (['ia_plan', 'ia_abono_hasta', 'ia_cupo_override', 'ia_abono_solicitado_at'] as $col) {
                if (Schema::hasColumn('empresas', $col)) {
                    $table->dropColumn($col);
                }
            }
        });
    }
};
