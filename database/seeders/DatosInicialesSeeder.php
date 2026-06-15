<?php

namespace Database\Seeders;

use App\Models\Empresa;
use App\Models\Sucursal;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatosInicialesSeeder extends Seeder
{
    public function run(): void
    {
        $empresa = Empresa::firstOrCreate(
            ['razon_social' => 'Mi Empresa'],
            [
                'nombre_fantasia' => 'POSMoon',
                'condicion_iva' => 'RESPONSABLE_INSCRIPTO',
            ]
        );

        $sucursal = Sucursal::firstOrCreate(
            ['empresa_id' => $empresa->id, 'nombre' => 'Casa Central'],
            ['codigo' => 'CC', 'activa' => true]
        );

        $admin = User::firstOrCreate(
            ['usuario' => 'admin'],
            [
                'name' => 'Administrador',
                'email' => 'admin@cmoon.local',
                'password' => Hash::make(env('ADMIN_PASSWORD') ?: 'CMoon2026!'),
                'empresa_id' => $empresa->id,
                'sucursal_id' => $sucursal->id,
                'activo' => true,
            ]
        );

        if (! $admin->hasRole('Administrador')) {
            $admin->assignRole('Administrador');
        }
    }
}
