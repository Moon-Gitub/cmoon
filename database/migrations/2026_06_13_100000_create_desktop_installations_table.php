<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('desktop_installations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('empresa_id')->constrained('empresas')->cascadeOnDelete();
            $table->unsignedInteger('moon_client_id')->comment('ID en BD Moon de cobros');
            $table->uuid('device_id')->unique();
            $table->string('device_name');
            $table->string('token_hash', 64)->unique();
            $table->timestamp('last_seen_at')->nullable();
            $table->timestamp('last_sync_at')->nullable();
            $table->boolean('activa')->default(true);
            $table->timestamps();

            $table->index(['empresa_id', 'moon_client_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('desktop_installations');
    }
};
