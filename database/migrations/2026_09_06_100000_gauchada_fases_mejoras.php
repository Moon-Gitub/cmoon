<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    private const PERMISOS = [
        'cobranzas.ver',
        'cheques.ver',
        'cheques.gestionar',
        'ordenes-compra.ver',
        'ordenes-compra.gestionar',
        'remitos.ver',
        'remitos.gestionar',
        'bancos.ver',
        'bancos.gestionar',
        'crm.ver',
        'crm.gestionar',
    ];

    public function up(): void
    {
        if (! Schema::hasColumn('movimientos_cuenta', 'vencimiento')) {
            Schema::table('movimientos_cuenta', function (Blueprint $table) {
                $table->date('vencimiento')->nullable()->after('fecha');
            });
        }

        if (! Schema::hasColumn('clientes', 'dias_credito')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->unsignedSmallInteger('dias_credito')->default(0)->after('limite_credito');
            });
        }

        if (! Schema::hasTable('cheques')) {
            Schema::create('cheques', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $table->foreignId('proveedor_id')->nullable()->constrained('proveedores')->nullOnDelete();
                $table->string('numero');
                $table->string('banco')->nullable();
                $table->string('plaza')->nullable();
                $table->date('fecha_emision');
                $table->date('fecha_vencimiento');
                $table->decimal('importe', 14, 2);
                $table->string('tipo', 20);
                $table->string('estado', 20)->default('en_cartera');
                $table->text('observaciones')->nullable();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamps();
                $table->index(['empresa_id', 'estado']);
                $table->index(['empresa_id', 'fecha_vencimiento']);
            });
        }

        if (! Schema::hasTable('ordenes_compra')) {
            Schema::create('ordenes_compra', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->constrained('sucursales');
                $table->foreignId('proveedor_id')->constrained('proveedores');
                $table->foreignId('user_id')->constrained('users');
                $table->unsignedInteger('numero');
                $table->string('estado', 20)->default('borrador');
                $table->date('fecha');
                $table->text('observaciones')->nullable();
                $table->decimal('total', 14, 2)->default(0);
                $table->timestamps();
                $table->unique(['empresa_id', 'numero']);
                $table->index(['empresa_id', 'fecha']);
            });
        }

        if (! Schema::hasTable('orden_compra_items')) {
            Schema::create('orden_compra_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('orden_compra_id')->constrained('ordenes_compra')->cascadeOnDelete();
                $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
                $table->string('descripcion');
                $table->decimal('cantidad', 12, 3);
                $table->decimal('cantidad_recibida', 12, 3)->default(0);
                $table->decimal('precio_unitario', 14, 2);
                $table->decimal('total', 14, 2);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('remitos')) {
            Schema::create('remitos', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('sucursal_id')->nullable()->constrained('sucursales')->nullOnDelete();
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
                $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
                $table->foreignId('user_id')->constrained('users');
                $table->unsignedInteger('numero');
                $table->string('estado', 20)->default('emitido');
                $table->date('fecha');
                $table->text('observaciones')->nullable();
                $table->timestamps();
                $table->unique(['empresa_id', 'numero']);
                $table->index(['empresa_id', 'fecha']);
            });
        }

        if (! Schema::hasTable('remito_items')) {
            Schema::create('remito_items', function (Blueprint $table) {
                $table->id();
                $table->foreignId('remito_id')->constrained('remitos')->cascadeOnDelete();
                $table->foreignId('producto_id')->nullable()->constrained('productos')->nullOnDelete();
                $table->string('descripcion');
                $table->decimal('cantidad', 12, 3);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('cuentas_bancarias')) {
            Schema::create('cuentas_bancarias', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->string('nombre');
                $table->string('banco')->nullable();
                $table->string('cbu')->nullable();
                $table->string('alias')->nullable();
                $table->string('moneda', 10)->default('ARS');
                $table->boolean('activa')->default(true);
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('movimientos_bancarios')) {
            Schema::create('movimientos_bancarios', function (Blueprint $table) {
                $table->id();
                $table->foreignId('cuenta_bancaria_id')->constrained('cuentas_bancarias')->cascadeOnDelete();
                $table->date('fecha');
                $table->string('concepto');
                $table->decimal('importe', 14, 2);
                $table->decimal('saldo', 14, 2)->nullable();
                $table->string('referencia_externa')->nullable();
                $table->boolean('conciliado')->default(false);
                $table->string('conciliado_con_type')->nullable();
                $table->unsignedBigInteger('conciliado_con_id')->nullable();
                $table->timestamps();
                $table->index(['cuenta_bancaria_id', 'fecha']);
                $table->index(['conciliado_con_type', 'conciliado_con_id'], 'mov_banc_conciliado_idx');
            });
        } elseif (! Schema::hasIndex('movimientos_bancarios', 'mov_banc_conciliado_idx')) {
            Schema::table('movimientos_bancarios', function (Blueprint $table) {
                $table->index(['conciliado_con_type', 'conciliado_con_id'], 'mov_banc_conciliado_idx');
            });
        }

        if (! Schema::hasTable('crm_oportunidades')) {
            Schema::create('crm_oportunidades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('cliente_id')->nullable()->constrained('clientes')->nullOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('titulo');
                $table->string('etapa', 20)->default('nuevo');
                $table->decimal('monto', 14, 2)->nullable();
                $table->unsignedTinyInteger('probabilidad')->default(0);
                $table->date('cierra_el')->nullable();
                $table->text('notas')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'etapa']);
            });
        }

        if (! Schema::hasTable('crm_actividades')) {
            Schema::create('crm_actividades', function (Blueprint $table) {
                $table->id();
                $table->foreignId('oportunidad_id')->constrained('crm_oportunidades')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->string('tipo', 30)->default('nota');
                $table->string('titulo');
                $table->text('cuerpo')->nullable();
                $table->timestamp('hecha_at')->nullable();
                $table->timestamps();
            });
        }

        if (! Schema::hasTable('facturas_recurrentes')) {
            Schema::create('facturas_recurrentes', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
                $table->foreignId('emisor_id')->nullable()->constrained('emisores')->nullOnDelete();
                $table->unsignedTinyInteger('dia_mes');
                $table->date('proximo_vencimiento');
                $table->string('concepto');
                $table->decimal('monto', 14, 2);
                $table->boolean('activa')->default(true);
                $table->timestamp('ultimo_envio_at')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'activa', 'proximo_vencimiento'], 'fact_rec_empresa_activa_idx');
            });
        }

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach (self::PERMISOS as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        Role::firstOrCreate(['name' => 'Administrador', 'guard_name' => 'web'])
            ->givePermissionTo(self::PERMISOS);

        Role::where('name', 'Vendedor')->where('guard_name', 'web')->first()
            ?->givePermissionTo('cobranzas.ver');
    }

    public function down(): void
    {
        Schema::dropIfExists('facturas_recurrentes');
        Schema::dropIfExists('crm_actividades');
        Schema::dropIfExists('crm_oportunidades');
        Schema::dropIfExists('movimientos_bancarios');
        Schema::dropIfExists('cuentas_bancarias');
        Schema::dropIfExists('remito_items');
        Schema::dropIfExists('remitos');
        Schema::dropIfExists('orden_compra_items');
        Schema::dropIfExists('ordenes_compra');
        Schema::dropIfExists('cheques');

        if (Schema::hasColumn('clientes', 'dias_credito')) {
            Schema::table('clientes', function (Blueprint $table) {
                $table->dropColumn('dias_credito');
            });
        }

        if (Schema::hasColumn('movimientos_cuenta', 'vencimiento')) {
            Schema::table('movimientos_cuenta', function (Blueprint $table) {
                $table->dropColumn('vencimiento');
            });
        }
    }
};
