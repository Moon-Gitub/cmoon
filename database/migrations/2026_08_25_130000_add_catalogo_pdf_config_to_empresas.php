<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->string('catalogo_fondo_path')->nullable()->after('logo_path');
            $table->string('catalogo_logo_path')->nullable()->after('catalogo_fondo_path');
            $table->string('catalogo_color_titulo', 7)->default('#909e23')->after('catalogo_logo_path');
            $table->string('catalogo_color_texto', 7)->default('#f1f0ec')->after('catalogo_color_titulo');
            $table->string('catalogo_share_token', 64)->nullable()->unique()->after('catalogo_color_texto');
        });
    }

    public function down(): void
    {
        Schema::table('empresas', function (Blueprint $table) {
            $table->dropColumn([
                'catalogo_fondo_path',
                'catalogo_logo_path',
                'catalogo_color_titulo',
                'catalogo_color_texto',
                'catalogo_share_token',
            ]);
        });
    }
};
