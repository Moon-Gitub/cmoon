<?php

namespace App\Support;

class Color
{
    /** Oscurece un color hex (#rrggbb) en un factor 0..1 */
    public static function oscurecer(string $hex, float $factor): string
    {
        $hex = ltrim($hex, '#');
        if (strlen($hex) !== 6 || ! ctype_xdigit($hex)) {
            return '#3730a3';
        }

        $rgb = array_map(
            fn ($par) => max(0, min(255, (int) round(hexdec($par) * (1 - $factor)))),
            str_split($hex, 2),
        );

        return sprintf('#%02x%02x%02x', ...$rgb);
    }
}
