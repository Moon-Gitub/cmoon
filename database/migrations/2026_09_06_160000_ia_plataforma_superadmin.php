<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            if (! Schema::hasColumn('users', 'es_superadmin')) {
                $table->boolean('es_superadmin')->default(false)->after('activo');
            }
        });

        Schema::table('empresas', function (Blueprint $table) {
            if (! Schema::hasColumn('empresas', 'ia_creditos_extra')) {
                $table->unsignedInteger('ia_creditos_extra')->default(0)->after('ia_cupo_override');
            }
        });

        if (! Schema::hasTable('ia_configuracion')) {
            Schema::create('ia_configuracion', function (Blueprint $table) {
                $table->id();
                $table->string('provider', 40)->default('openai'); // openai|openrouter|groq|custom
                $table->text('api_key')->nullable(); // encrypted
                $table->string('base_url')->nullable();
                $table->string('model')->nullable();
                $table->boolean('activo')->default(true);
                $table->timestamps();
            });

            DB::table('ia_configuracion')->insert([
                'provider' => 'openai',
                'api_key' => null,
                'base_url' => 'https://api.openai.com/v1',
                'model' => 'gpt-4o-mini',
                'activo' => true,
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        if (! Schema::hasTable('ia_paquetes')) {
            Schema::create('ia_paquetes', function (Blueprint $table) {
                $table->id();
                $table->string('nombre');
                $table->unsignedInteger('creditos');
                $table->decimal('precio', 12, 2)->default(0);
                $table->string('moneda', 10)->default('ARS');
                $table->boolean('activo')->default(true);
                $table->unsignedSmallInteger('orden')->default(0);
                $table->timestamps();
            });

            $now = now();
            DB::table('ia_paquetes')->insert([
                ['nombre' => 'Básico', 'creditos' => 100, 'precio' => 4900, 'moneda' => 'ARS', 'activo' => true, 'orden' => 1, 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'Pro', 'creditos' => 500, 'precio' => 14900, 'moneda' => 'ARS', 'activo' => true, 'orden' => 2, 'created_at' => $now, 'updated_at' => $now],
                ['nombre' => 'Mega', 'creditos' => 2000, 'precio' => 39900, 'moneda' => 'ARS', 'activo' => true, 'orden' => 3, 'created_at' => $now, 'updated_at' => $now],
            ]);
        }

        if (! Schema::hasTable('ia_compras')) {
            Schema::create('ia_compras', function (Blueprint $table) {
                $table->id();
                $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
                $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
                $table->foreignId('paquete_id')->nullable()->constrained('ia_paquetes')->nullOnDelete();
                $table->unsignedInteger('creditos');
                $table->decimal('precio', 12, 2)->default(0);
                $table->string('estado', 20)->default('solicitada'); // solicitada|aprobada|rechazada
                $table->text('notas')->nullable();
                $table->foreignId('aprobado_por')->nullable()->constrained('users')->nullOnDelete();
                $table->timestamp('aprobado_at')->nullable();
                $table->timestamps();
                $table->index(['empresa_id', 'estado']);
            });
        }

        // El acceso real al panel es users.es_superadmin (no el rol Administrador del tenant).
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        Permission::firstOrCreate(['name' => 'ia.superadmin', 'guard_name' => 'web']);

        $email = trim((string) env('SUPERADMIN_EMAIL', ''));
        if ($email !== '' && Schema::hasColumn('users', 'es_superadmin')) {
            DB::table('users')->where('email', $email)->update(['es_superadmin' => true]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('ia_compras');
        Schema::dropIfExists('ia_paquetes');
        Schema::dropIfExists('ia_configuracion');

        if (Schema::hasColumn('empresas', 'ia_creditos_extra')) {
            Schema::table('empresas', function (Blueprint $table) {
                $table->dropColumn('ia_creditos_extra');
            });
        }

        if (Schema::hasColumn('users', 'es_superadmin')) {
            Schema::table('users', function (Blueprint $table) {
                $table->dropColumn('es_superadmin');
            });
        }
    }
};
