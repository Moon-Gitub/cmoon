<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('billing_customers', function (Blueprint $table) {
            $table->id();
            $table->unsignedInteger('legacy_id')->nullable()->unique();
            $table->string('nombre');
            $table->string('razon_social')->nullable();
            $table->string('cuit', 20)->nullable();
            $table->string('email')->nullable();
            $table->string('telefono', 40)->nullable();
            $table->string('dominio')->nullable();
            $table->string('plataforma', 20)->default('legacy');
            $table->string('estado', 20)->default('activo');
            $table->string('modo_cobro', 20)->default('solo_aviso');
            $table->boolean('aplicar_recargos')->default(true);
            $table->date('prorroga_hasta')->nullable();
            $table->string('pay_token', 64)->unique();
            $table->string('api_key_hash', 64)->nullable()->unique();
            $table->text('notas')->nullable();
            $table->timestamps();
            $table->index('estado');
        });

        Schema::create('billing_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('billing_customers')->cascadeOnDelete();
            $table->string('concepto');
            $table->bigInteger('importe_centavos');
            $table->date('desde');
            $table->date('hasta')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('billing_invoices', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('billing_customers')->cascadeOnDelete();
            $table->foreignId('subscription_id')->nullable()->constrained('billing_subscriptions')->nullOnDelete();
            $table->char('periodo', 7);
            $table->string('concepto');
            $table->bigInteger('importe_centavos');
            $table->date('vencimiento');
            $table->timestamps();
            $table->unique(['subscription_id', 'periodo']);
            $table->index(['customer_id', 'periodo']);
        });

        Schema::create('billing_payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->nullable()->constrained('billing_customers')->nullOnDelete();
            $table->string('proveedor', 30);
            $table->string('external_id', 100);
            $table->string('estado', 30);
            $table->string('estado_detalle', 100)->nullable();
            $table->bigInteger('importe_centavos');
            $table->bigInteger('neto_centavos')->nullable();
            $table->string('metodo', 50)->nullable();
            $table->string('tipo_pago', 50)->nullable();
            $table->unsignedBigInteger('checkout_id')->nullable();
            $table->timestamp('aprobado_at')->nullable();
            $table->timestamp('acreditado_at')->nullable();
            $table->json('payload')->nullable();
            $table->timestamps();
            $table->unique(['proveedor', 'external_id']);
            $table->index(['customer_id', 'estado']);
        });

        Schema::create('billing_ledger_entries', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('billing_customers')->cascadeOnDelete();
            $table->dateTime('fecha');
            $table->string('tipo', 30);
            $table->string('descripcion');
            $table->bigInteger('importe_centavos');
            $table->string('referencia', 120)->unique();
            $table->foreignId('payment_id')->nullable()->constrained('billing_payments')->nullOnDelete();
            $table->foreignId('invoice_id')->nullable()->constrained('billing_invoices')->nullOnDelete();
            $table->string('origen', 30);
            $table->unsignedBigInteger('user_id')->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'fecha']);
            $table->index('tipo');
        });

        Schema::create('billing_checkouts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('billing_customers')->cascadeOnDelete();
            $table->string('external_reference', 60)->unique();
            $table->string('preference_id', 100)->nullable();
            $table->text('init_point')->nullable();
            $table->string('modo', 20);
            $table->bigInteger('importe_centavos');
            $table->bigInteger('recargo_centavos')->default(0);
            $table->char('recargo_periodo', 7)->nullable();
            $table->json('detalle')->nullable();
            $table->string('estado', 20)->default('abierto');
            $table->timestamp('expira_at');
            $table->timestamp('pagado_at')->nullable();
            $table->timestamps();
            $table->index(['customer_id', 'estado']);
        });

        Schema::create('billing_webhook_events', function (Blueprint $table) {
            $table->id();
            $table->string('proveedor', 30);
            $table->string('topic', 60)->nullable();
            $table->string('resource_id', 100)->nullable();
            $table->string('request_id', 100)->nullable();
            $table->boolean('firma_valida')->default(false);
            $table->json('payload')->nullable();
            $table->json('headers')->nullable();
            $table->timestamp('procesado_at')->nullable();
            $table->unsignedInteger('intentos')->default(0);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->index(['topic', 'resource_id']);
            $table->index('procesado_at');
        });

        Schema::create('billing_notifications', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('billing_customers')->cascadeOnDelete();
            $table->string('tipo', 30);
            $table->string('periodo', 40);
            $table->string('canal', 20);
            $table->string('destino')->nullable();
            $table->string('estado', 20);
            $table->text('error')->nullable();
            $table->timestamps();
            $table->unique(['customer_id', 'tipo', 'periodo', 'canal']);
        });

        Schema::create('billing_audit_logs', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_id')->nullable();
            $table->unsignedBigInteger('user_id')->nullable();
            $table->string('accion', 60);
            $table->string('nivel', 10)->default('info');
            $table->json('detalle')->nullable();
            $table->string('ip', 45)->nullable();
            $table->timestamp('created_at')->useCurrent();
            $table->index(['customer_id', 'created_at']);
            $table->index(['nivel', 'created_at']);
        });

        Schema::create('billing_reconciliations', function (Blueprint $table) {
            $table->id();
            $table->dateTime('desde');
            $table->dateTime('hasta');
            $table->string('estado', 20);
            $table->unsignedInteger('revisados')->default(0);
            $table->unsignedInteger('acreditados')->default(0);
            $table->json('alertas')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        foreach ([
            'billing_reconciliations', 'billing_audit_logs', 'billing_notifications',
            'billing_webhook_events', 'billing_checkouts', 'billing_ledger_entries',
            'billing_payments', 'billing_invoices', 'billing_subscriptions', 'billing_customers',
        ] as $tabla) {
            Schema::dropIfExists($tabla);
        }
    }
};
