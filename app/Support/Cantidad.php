<?php

namespace App\Support;

class Cantidad
{
    /** Entero si es redondo; si no, hasta 2 decimales sin ceros de más (ej. 4 · 1,5 · 0,25). */
    public static function format(float|int|string|null $valor, string $dec = ',', string $miles = '.'): string
    {
        $n = round((float) $valor, 2);

        if (abs($n - round($n)) < 0.0000001) {
            return number_format((int) round($n), 0, $dec, $miles);
        }

        return rtrim(rtrim(number_format($n, 2, $dec, $miles), '0'), $dec);
    }

    /** Valor para &lt;input type="number"&gt; (punto decimal, sin miles). */
    public static function input(float|int|string|null $valor): string
    {
        $n = round((float) $valor, 2);

        if (abs($n - round($n)) < 0.0000001) {
            return (string) (int) round($n);
        }

        return rtrim(rtrim(sprintf('%.2f', $n), '0'), '.');
    }
}
