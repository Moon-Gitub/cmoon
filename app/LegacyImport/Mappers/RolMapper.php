<?php

namespace App\LegacyImport\Mappers;

class RolMapper
{
    public static function toSpatie(?string $perfil): string
    {
        $p = trim((string) $perfil);

        return match (true) {
            strcasecmp($p, 'Administrador') === 0 => 'Administrador',
            strcasecmp($p, 'Vendedor') === 0 => 'Vendedor',
            strcasecmp($p, 'Cajero') === 0 => 'Cajero',
            default => 'Cajero',
        };
    }
}
