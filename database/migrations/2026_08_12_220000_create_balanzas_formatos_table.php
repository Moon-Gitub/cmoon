<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('balanzas_formatos', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('nombre', 128);
            $table->string('prefijo', 32);
            $table->unsignedInteger('longitud_min')->nullable();
            $table->unsignedInteger('longitud_max')->nullable();
            $table->unsignedInteger('pos_producto')->default(0);
            $table->unsignedInteger('longitud_producto')->default(0);
            $table->string('modo_cantidad', 16)->default('ninguno'); // peso|unidad|ninguno
            $table->unsignedInteger('pos_cantidad')->nullable();
            $table->unsignedInteger('longitud_cantidad')->nullable();
            $table->decimal('factor_divisor', 10, 4)->default(1);
            $table->decimal('cantidad_fija', 10, 3)->default(1);
            $table->integer('orden')->default(0);
            $table->boolean('activo')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'activo', 'orden']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('balanzas_formatos');
    }
};
