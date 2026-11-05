<?php

namespace App\Billing\Support;

use InvalidArgumentException;

final class Money
{
    public static function formato(int $centavos): string
    {
        $signo = $centavos < 0 ? '-' : '';

        return $signo.'$'.number_format(abs($centavos) / 100, 2, ',', '.');
    }

    public static function pesos(int $centavos): float
    {
        return round($centavos / 100, 2);
    }

    /** Acepta "7500", "7.500,50", "7500.50", 7500.5 */
    public static function centavos(string|int|float $valor): int
    {
        if (is_int($valor)) {
            return $valor * 100;
        }
        if (is_float($valor)) {
            return (int) round($valor * 100);
        }

        $v = trim(str_replace(['$', ' '], '', $valor));
        if (preg_match('/^-?\d{1,3}(\.\d{3})+(,\d{1,2})?$/', $v) || str_contains($v, ',')) {
            $v = str_replace(['.', ','], ['', '.'], $v);
        }
        if (! is_numeric($v)) {
            throw new InvalidArgumentException("Importe inválido: {$valor}");
        }

        return (int) round(((float) $v) * 100);
    }
}
