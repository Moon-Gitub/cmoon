<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('clientes', function (Blueprint $table) {
            $table->foreignId('vendedor_id')->nullable()->after('lista_precio_id')
                ->constrained('users')->nullOnDelete();
            $table->decimal('lat', 10, 7)->nullable()->after('localidad');
            $table->decimal('lng', 10, 7)->nullable()->after('lat');
        });

        Schema::table('movimientos_cuenta', function (Blueprint $table) {
            $table->uuid('uuid')->nullable()->unique()->after('id');
        });

        Schema::create('rutas', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('nombre');
            $table->unsignedTinyInteger('dia_semana')->nullable()->comment('0=dom … 6=sáb, null=cualquier día');
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('ruta_clientes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('ruta_id')->constrained('rutas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->unsignedSmallInteger('orden')->default(0);
            $table->unique(['ruta_id', 'cliente_id']);
        });

        Schema::create('visitas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('ruta_id')->nullable()->constrained('rutas')->nullOnDelete();
            $table->string('estado', 20)->default('visitada');
            $table->date('fecha');
            $table->decimal('lat', 10, 7)->nullable();
            $table->decimal('lng', 10, 7)->nullable();
            $table->text('observaciones')->nullable();
            $table->timestamp('checkin_at')->nullable();
            $table->timestamps();
        });

        Schema::create('entregas', function (Blueprint $table) {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->foreignId('cliente_id')->constrained('clientes')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('presupuesto_id')->nullable()->constrained('presupuestos')->nullOnDelete();
            $table->foreignId('venta_id')->nullable()->constrained('ventas')->nullOnDelete();
            $table->string('estado', 20)->default('entregada');
            $table->text('observaciones')->nullable();
            $table->string('firma_path')->nullable();
            $table->timestamp('entregado_at')->nullable();
            $table->timestamps();
        });

        Schema::create('entrega_fotos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('entrega_id')->constrained('entregas')->cascadeOnDelete();
            $table->string('path');
            $table->timestamps();
        });

        app(PermissionRegistrar::class)->forgetCachedPermissions();

        foreach ([
            'cobranzas.crear_movil',
            'rutas.gestionar',
            'rutas.ver_movil',
            'entregas.confirmar_movil',
            'reportes.vendedor_movil',
        ] as $permiso) {
            Permission::firstOrCreate(['name' => $permiso, 'guard_name' => 'web']);
        }

        $admin = Role::findByName('Administrador', 'web');
        $vendedor = Role::findByName('Vendedor', 'web');
        $admin?->givePermissionTo([
            'cobranzas.crear_movil', 'rutas.gestionar', 'rutas.ver_movil',
            'entregas.confirmar_movil', 'reportes.vendedor_movil',
        ]);
        $vendedor?->givePermissionTo([
            'cobranzas.crear_movil', 'rutas.ver_movil', 'entregas.confirmar_movil', 'reportes.vendedor_movil',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('entrega_fotos');
        Schema::dropIfExists('entregas');
        Schema::dropIfExists('visitas');
        Schema::dropIfExists('ruta_clientes');
        Schema::dropIfExists('rutas');

        Schema::table('movimientos_cuenta', function (Blueprint $table) {
            $table->dropColumn('uuid');
        });

        Schema::table('clientes', function (Blueprint $table) {
            $table->dropConstrainedForeignId('vendedor_id');
            $table->dropColumn(['lat', 'lng']);
        });
    }
};
