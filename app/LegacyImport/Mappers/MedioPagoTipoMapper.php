<?php

namespace App\LegacyImport\Mappers;

class MedioPagoTipoMapper
{
    public static function fromLegacyCodigoOrNombre(?string $codigo, ?string $nombre): string
    {
        $haystack = strtolower(trim(($codigo ?? '').' '.($nombre ?? '')));

        return match (true) {
            str_contains($haystack, 'ef') || str_contains($haystack, 'efectivo') => 'efectivo',
            str_contains($haystack, 'td') || str_contains($haystack, 'debito') || str_contains($haystack, 'débito') => 'tarjeta_debito',
            str_contains($haystack, 'tc') || str_contains($haystack, 'credito') || str_contains($haystack, 'crédito') => 'tarjeta_credito',
            str_contains($haystack, 'transf') || str_contains($haystack, 'transfer') => 'transferencia',
            str_contains($haystack, 'qr') || str_contains($haystack, 'mercado') => 'qr',
            str_contains($haystack, 'cheque') => 'cheque',
            str_contains($haystack, 'cc') || str_contains($haystack, 'cuenta') => 'cuenta_corriente',
            default => 'otro',
        };
    }

    public static function fromMetodoPagoJson(string $tipo): string
    {
        return self::fromLegacyCodigoOrNombre($tipo, $tipo);
    }
}
