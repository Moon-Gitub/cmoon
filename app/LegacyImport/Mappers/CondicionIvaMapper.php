<?php

namespace App\LegacyImport\Mappers;

class CondicionIvaMapper
{
    /** @var array<int|string, string> */
    private const MAP = [
        1 => 'RESPONSABLE_INSCRIPTO',
        2 => 'EXENTO',
        3 => 'RESPONSABLE_INSCRIPTO',
        4 => 'CONSUMIDOR_FINAL',
        5 => 'CONSUMIDOR_FINAL',
        6 => 'MONOTRIBUTO',
        7 => 'CONSUMIDOR_FINAL',
        11 => 'RESPONSABLE_INSCRIPTO',
        13 => 'MONOTRIBUTO',
    ];

    public static function toCmoon(int|string|null $legacy): string
    {
        if ($legacy === null || $legacy === '') {
            return 'CONSUMIDOR_FINAL';
        }

        return self::MAP[(int) $legacy] ?? 'CONSUMIDOR_FINAL';
    }
}
