<?php

namespace App\LegacyImport\Mappers;

class TipoDocumentoMapper
{
    /** @var array<int, string> */
    private const MAP = [
        80 => 'CUIT',
        86 => 'CUIL',
        96 => 'DNI',
        99 => 'OTRO',
        0 => 'OTRO',
    ];

    public static function toCmoon(int|string|null $legacy): string
    {
        if ($legacy === null || $legacy === '') {
            return 'DNI';
        }

        return self::MAP[(int) $legacy] ?? 'OTRO';
    }
}
