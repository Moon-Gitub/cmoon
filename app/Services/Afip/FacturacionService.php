<?php

namespace App\Services\Afip;

use App\Models\Comprobante;
use App\Models\Emisor;
use App\Models\PuntoVenta;
use App\Models\Venta;
use Illuminate\Validation\ValidationException;

/**
 * Arma el comprobante fiscal a partir de una venta y pide el CAE.
 * Los precios del POS son finales (IVA incluido): acá se desglosa.
 */
class FacturacionService
{
    public function __construct(private WsfeService $wsfe) {}

    public function facturarVenta(Venta $venta, Emisor $emisor, PuntoVenta $puntoVenta, int $userId): Comprobante
    {
        if ($venta->estado !== 'completada') {
            throw ValidationException::withMessages(['venta' => 'Solo se pueden facturar ventas completadas.']);
        }

        $yaFacturada = Comprobante::where('venta_id', $venta->id)
            ->whereIn('estado', ['autorizado', 'pendiente'])
            ->exists();

        if ($yaFacturada) {
            throw ValidationException::withMessages(['venta' => 'Esta venta ya tiene un comprobante emitido o en proceso.']);
        }

        $cliente = $venta->cliente;
        $tipo = $this->tipoComprobante($emisor, $cliente?->condicion_iva);

        [$docTipo, $docNumero] = $this->documentoReceptor($cliente, (float) $venta->total);

        // Desglose de IVA por alícuota (precios finales → neto = total / (1 + iva))
        $porAlicuota = [];
        foreach ($venta->items as $item) {
            $alicuota = number_format((float) $item->alicuota_iva, 2, '.', '');
            $porAlicuota[$alicuota] = ($porAlicuota[$alicuota] ?? 0) + (float) $item->total;
        }

        // El descuento/recargo global se prorratea sobre todas las alícuotas
        $factorGlobal = (float) $venta->subtotal > 0
            ? (float) $venta->total / (float) $venta->subtotal
            : 1.0;

        $detalleIva = [];
        $netoTotal = 0.0;
        $ivaTotal = 0.0;

        foreach ($porAlicuota as $alicuota => $bruto) {
            $brutoFinal = round($bruto * $factorGlobal, 2);
            $tasa = (float) $alicuota / 100;
            $neto = round($brutoFinal / (1 + $tasa), 2);
            $iva = round($brutoFinal - $neto, 2);

            $detalleIva[] = ['alicuota' => (float) $alicuota, 'neto' => $neto, 'iva' => $iva];
            $netoTotal += $neto;
            $ivaTotal += $iva;
        }

        // Ajuste por redondeo contra el total real
        $diferencia = round((float) $venta->total - ($netoTotal + $ivaTotal), 2);
        if (abs($diferencia) >= 0.01 && $detalleIva !== []) {
            $detalleIva[0]['neto'] = round($detalleIva[0]['neto'] + $diferencia, 2);
            $netoTotal += $diferencia;
        }

        $comprobante = Comprobante::create([
            'venta_id' => $venta->id,
            'emisor_id' => $emisor->id,
            'punto_venta_id' => $puntoVenta->id,
            'user_id' => $userId,
            'tipo_comprobante' => $tipo,
            'doc_tipo' => $docTipo,
            'doc_numero' => $docNumero,
            'receptor_nombre' => $cliente?->nombre ?? 'Consumidor final',
            'receptor_condicion_iva' => $cliente?->condicion_iva ?? 'CONSUMIDOR_FINAL',
            'neto' => round($netoTotal, 2),
            'iva' => round($ivaTotal, 2),
            'total' => (float) $venta->total,
            'detalle_iva' => $detalleIva,
            'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(),
        ]);

        try {
            return $this->wsfe->autorizar($comprobante);
        } catch (\Throwable $e) {
            $comprobante->update([
                'estado' => 'error',
                'mensaje_afip' => $e->getMessage(),
            ]);

            return $comprobante;
        }
    }

    public function tipoComprobante(Emisor $emisor, ?string $condicionReceptor): int
    {
        if ($emisor->esMonotributo()) {
            return 11; // Factura C
        }

        return $condicionReceptor === 'RESPONSABLE_INSCRIPTO' ? 1 : 6; // A o B
    }

    private function documentoReceptor(?object $cliente, float $total): array
    {
        $documento = preg_replace('/\D/', '', (string) ($cliente->documento ?? ''));

        if ($documento === '') {
            return [99, '0']; // Consumidor final sin identificar
        }

        $docTipo = match ($cliente->tipo_documento ?? 'DNI') {
            'CUIT' => 80,
            'CUIL' => 86,
            'DNI' => 96,
            default => 99,
        };

        return [$docTipo, $documento];
    }
}
