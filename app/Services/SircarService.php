<?php

namespace App\Services;

use App\Models\Retencion;
use Illuminate\Support\Collection;

/**
 * Genera el TXT de retenciones para SIRCAR — Diseño N° 1 (11 campos
 * separados por coma, CRLF). Jurisdicción 913 = Mendoza.
 */
class SircarService
{
    /**
     * @param Collection<int, Retencion> $retenciones
     */
    public function generarTxt(Collection $retenciones): string
    {
        $lineas = [];
        $renglon = 1;

        foreach ($retenciones as $retencion) {
            $lineas[] = implode(',', [
                str_pad((string) $renglon++, 5, '0', STR_PAD_LEFT),
                '1',
                $retencion->anulada ? '2' : '1',
                $this->formatearComprobante($retencion->factura_numero),
                $this->formatearCuit($retencion->proveedor->cuit ?? ''),
                $retencion->fecha->format('d/m/Y'),
                number_format((float) $retencion->factura_neto, 2, '.', ''),
                number_format((float) $retencion->alicuota, 2, '.', ''),
                number_format((float) $retencion->monto, 2, '.', ''),
                (string) $retencion->regimen,
                (string) $retencion->jurisdiccion,
            ]);
        }

        return implode("\r\n", $lineas).($lineas !== [] ? "\r\n" : '');
    }

    private function formatearComprobante(string $numero): string
    {
        $digitos = preg_replace('/\D/', '', $numero) ?: '0';

        return str_pad(substr($digitos, -12), 12, '0', STR_PAD_LEFT);
    }

    private function formatearCuit(string $cuit): string
    {
        $digitos = preg_replace('/\D/', '', $cuit) ?: '0';

        return str_pad(substr($digitos, -11), 11, '0', STR_PAD_LEFT);
    }
}
