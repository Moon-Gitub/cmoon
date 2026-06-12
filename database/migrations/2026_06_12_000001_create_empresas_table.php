<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('empresas', function (Blueprint $table) {
            $table->id();
            $table->string('razon_social');
            $table->string('nombre_fantasia')->nullable();
            $table->string('cuit', 13)->nullable()->unique();
            $table->string('condicion_iva', 30)->default('RESPONSABLE_INSCRIPTO');
            $table->string('ingresos_brutos', 30)->nullable();
            $table->date('inicio_actividades')->nullable();
            $table->string('domicilio')->nullable();
            $table->string('localidad')->nullable();
            $table->string('provincia')->nullable();
            $table->string('codigo_postal', 10)->nullable();
            $table->string('telefono', 30)->nullable();
            $table->string('email')->nullable();
            $table->string('logo_path')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();
        });

        Schema::create('sucursales', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre');
            $table->string('codigo', 10)->nullable();
            $table->string('domicilio')->nullable();
            $table->string('telefono', 30)->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->unique(['empresa_id', 'nombre']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sucursales');
        Schema::dropIfExists('empresas');
    }
};
