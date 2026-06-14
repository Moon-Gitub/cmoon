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
        $venta->loadMissing(['items', 'cliente']);

        if ($venta->estado !== 'completada') {
            throw ValidationException::withMessages(['venta' => 'Solo se pueden facturar ventas completadas.']);
        }

        if ($venta->items->isEmpty()) {
            throw ValidationException::withMessages(['venta' => 'La venta no tiene ítems para facturar.']);
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

        $porAlicuota = array_map(fn ($bruto) => round($bruto * $factorGlobal, 2), $porAlicuota);

        [$detalleIva, $netoTotal, $ivaTotal] = $this->desglosarIva($porAlicuota, (float) $venta->total);

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
            'neto' => $netoTotal,
            'iva' => $ivaTotal,
            'total' => (float) $venta->total,
            'detalle_iva' => $detalleIva,
            'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(),
        ]);

        return $this->autorizarSeguro($comprobante);
    }

    /**
     * Factura manual: comprobante con ítems libres, sin venta asociada.
     * $datos: receptor_nombre, receptor_condicion_iva, doc_tipo, doc_numero,
     *         items: [{descripcion, cantidad, precio_unitario, alicuota_iva}]
     */
    public function facturaManual(array $datos, Emisor $emisor, PuntoVenta $puntoVenta, int $userId): Comprobante
    {
        $items = [];
        $porAlicuota = [];
        $total = 0.0;

        foreach ($datos['items'] as $item) {
            $cantidad = (float) $item['cantidad'];
            $precio = (float) $item['precio_unitario'];
            $alicuota = number_format((float) ($item['alicuota_iva'] ?? 21), 2, '.', '');
            $totalItem = round($cantidad * $precio, 2);

            $items[] = [
                'descripcion' => $item['descripcion'],
                'cantidad' => $cantidad,
                'precio_unitario' => $precio,
                'alicuota_iva' => (float) $alicuota,
                'total' => $totalItem,
            ];
            $porAlicuota[$alicuota] = ($porAlicuota[$alicuota] ?? 0) + $totalItem;
            $total += $totalItem;
        }

        [$detalleIva, $neto, $iva] = $this->desglosarIva($porAlicuota, round($total, 2));

        $comprobante = Comprobante::create([
            'emisor_id' => $emisor->id,
            'punto_venta_id' => $puntoVenta->id,
            'user_id' => $userId,
            'tipo_comprobante' => $this->tipoComprobante($emisor, $datos['receptor_condicion_iva'] ?? null),
            'doc_tipo' => (int) ($datos['doc_tipo'] ?? 99),
            'doc_numero' => preg_replace('/\D/', '', (string) ($datos['doc_numero'] ?? '')) ?: '0',
            'receptor_nombre' => $datos['receptor_nombre'] ?: 'Consumidor final',
            'receptor_condicion_iva' => $datos['receptor_condicion_iva'] ?? 'CONSUMIDOR_FINAL',
            'neto' => $neto,
            'iva' => $iva,
            'total' => round($total, 2),
            'detalle_iva' => $detalleIva,
            'detalle_items' => $items,
            'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(),
        ]);

        return $this->autorizarSeguro($comprobante);
    }

    /**
     * Nota de crédito o débito sobre un comprobante autorizado.
     * Si $importe es null, anula el total del comprobante original.
     */
    public function nota(Comprobante $original, string $clase, ?float $importe, string $concepto, int $userId): Comprobante
    {
        if ($original->estado !== 'autorizado') {
            throw ValidationException::withMessages(['comprobante' => 'Solo se pueden emitir notas sobre comprobantes autorizados.']);
        }

        $tipo = $this->tipoNota($original->tipo_comprobante, $clase);
        $importe = round($importe ?? (float) $original->total, 2);

        if ($importe <= 0 || $importe > (float) $original->total) {
            throw ValidationException::withMessages(['importe' => 'El importe tiene que ser mayor a cero y no superar el total del comprobante original.']);
        }

        // Prorratear el importe sobre el desglose de IVA original
        $factor = $importe / (float) $original->total;
        $porAlicuota = [];
        foreach ($original->detalle_iva ?? [] as $fila) {
            $alicuota = number_format((float) $fila['alicuota'], 2, '.', '');
            $bruto = round(((float) $fila['neto'] + (float) $fila['iva']) * $factor, 2);
            $porAlicuota[$alicuota] = ($porAlicuota[$alicuota] ?? 0) + $bruto;
        }
        if ($porAlicuota === []) {
            $porAlicuota['21.00'] = $importe;
        }

        [$detalleIva, $neto, $iva] = $this->desglosarIva($porAlicuota, $importe);

        $comprobante = Comprobante::create([
            'venta_id' => $original->venta_id,
            'comprobante_asociado_id' => $original->id,
            'emisor_id' => $original->emisor_id,
            'punto_venta_id' => $original->punto_venta_id,
            'user_id' => $userId,
            'tipo_comprobante' => $tipo,
            'doc_tipo' => $original->doc_tipo,
            'doc_numero' => $original->doc_numero,
            'receptor_nombre' => $original->receptor_nombre,
            'receptor_condicion_iva' => $original->receptor_condicion_iva,
            'neto' => $neto,
            'iva' => $iva,
            'total' => $importe,
            'detalle_iva' => $detalleIva,
            'detalle_items' => [[
                'descripcion' => $concepto,
                'cantidad' => 1,
                'precio_unitario' => $importe,
                'alicuota_iva' => 21,
                'total' => $importe,
            ]],
            'concepto' => $concepto,
            'estado' => 'pendiente',
            'fecha_emision' => now()->toDateString(),
        ]);

        return $this->autorizarSeguro($comprobante);
    }

    private function autorizarSeguro(Comprobante $comprobante): Comprobante
    {
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

    /**
     * Desglosa importes finales (IVA incluido) por alícuota.
     * Devuelve [detalleIva, netoTotal, ivaTotal] ajustado al total exacto.
     */
    private function desglosarIva(array $porAlicuota, float $total): array
    {
        $detalleIva = [];
        $netoTotal = 0.0;
        $ivaTotal = 0.0;

        foreach ($porAlicuota as $alicuota => $bruto) {
            $tasa = (float) $alicuota / 100;
            $neto = round($bruto / (1 + $tasa), 2);
            $iva = round($bruto - $neto, 2);

            $detalleIva[] = ['alicuota' => (float) $alicuota, 'neto' => $neto, 'iva' => $iva];
            $netoTotal += $neto;
            $ivaTotal += $iva;
        }

        if ($detalleIva === [] && $total > 0) {
            $detalleIva[] = ['alicuota' => 21.0, 'neto' => round($total / 1.21, 2), 'iva' => round($total - ($total / 1.21), 2)];
            $netoTotal = $detalleIva[0]['neto'];
            $ivaTotal = $detalleIva[0]['iva'];
        }

        $diferencia = round($total - ($netoTotal + $ivaTotal), 2);
        if (abs($diferencia) >= 0.01 && $detalleIva !== []) {
            $detalleIva[0]['neto'] = round($detalleIva[0]['neto'] + $diferencia, 2);
            $netoTotal += $diferencia;
        }

        return [$detalleIva, round($netoTotal, 2), round($ivaTotal, 2)];
    }

    private function tipoNota(int $tipoOriginal, string $clase): int
    {
        $letra = match (true) {
            in_array($tipoOriginal, [1, 2, 3]) => 'A',
            in_array($tipoOriginal, [6, 7, 8]) => 'B',
            default => 'C',
        };

        return match ([$clase, $letra]) {
            ['credito', 'A'] => 3,
            ['credito', 'B'] => 8,
            ['credito', 'C'] => 13,
            ['debito', 'A'] => 2,
            ['debito', 'B'] => 7,
            ['debito', 'C'] => 12,
        };
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
