<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('legacy_import_maps', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->string('entity', 50)->comment('producto, cliente, venta, etc.');
            $table->string('legacy_id', 50);
            $table->unsignedBigInteger('new_id');
            $table->timestamps();

            $table->unique(['empresa_id', 'entity', 'legacy_id'], 'legacy_import_maps_unique');
            $table->index(['empresa_id', 'entity']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('legacy_import_maps');
    }
};
