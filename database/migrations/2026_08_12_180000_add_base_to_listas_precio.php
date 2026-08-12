<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('listas_precio', function (Blueprint $table) {
            $table->string('base', 20)->default('venta')
                ->after('porcentaje')
                ->comment('venta = precio_venta; compra = precio_compra (al costo)');
        });

        // Listas típicas "al costo" importadas desde demonew.
        DB::table('listas_precio')
            ->where(function ($q) {
                $q->where('nombre', 'like', '%Costo%')
                    ->orWhere('nombre', 'like', '%costo%');
            })
            ->update(['base' => 'compra']);
    }

    public function down(): void
    {
        Schema::table('listas_precio', function (Blueprint $table) {
            $table->dropColumn('base');
        });
    }
};
