<?php

namespace App\LegacyImport\Support;

use Illuminate\Support\Facades\DB;
use RuntimeException;

class LegacyConnection
{
    public static function assertAvailable(): void
    {
        if (! config('legacy.enabled')) {
            throw new RuntimeException(
                'Import legacy deshabilitado. Definí LEGACY_IMPORT_ENABLED=true en .env para ejecutar legacy:import.'
            );
        }

        $connection = config('legacy.connection');
        $config = config("database.connections.{$connection}");

        if (empty($config['database']) || empty($config['username'])) {
            throw new RuntimeException(
                'Faltan credenciales LEGACY_DB_* en .env (host, database, username).'
            );
        }

        try {
            DB::connection($connection)->getPdo();
        } catch (\Throwable $e) {
            throw new RuntimeException('No se pudo conectar a la BD legacy: '.$e->getMessage(), 0, $e);
        }
    }
}
