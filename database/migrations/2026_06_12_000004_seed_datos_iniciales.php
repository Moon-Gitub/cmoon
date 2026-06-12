<?php

use Database\Seeders\DatosInicialesSeeder;
use Database\Seeders\RolesYPermisosSeeder;
use Illuminate\Database\Migrations\Migration;

/**
 * Bootstrap de datos base (roles, permisos, empresa, admin) dentro de una
 * migración para que el deploy quede funcional sin pasos manuales.
 * Los seeders son idempotentes (firstOrCreate).
 */
return new class extends Migration
{
    public function up(): void
    {
        (new RolesYPermisosSeeder)->run();
        (new DatosInicialesSeeder)->run();
    }

    public function down(): void
    {
        // Datos base: no se revierten
    }
};
