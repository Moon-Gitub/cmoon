<?php

namespace App\Services;

use App\Models\CajaMovimiento;
use App\Models\CajaSesion;
use App\Models\MedioPago;
use App\Models\MovimientoCuenta;
use App\Models\Proveedor;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ProveedorCuentaCorrienteService
{
    public function __construct(
        private readonly RetencionService $retenciones,
    ) {}

    public function registrarFactura(Proveedor $proveedor, User $user, array $datos): MovimientoCuenta
    {
        $netoPrevio = (float) $datos['neto_previo'];
        $descuento = (float) ($datos['descuento'] ?? 0);
        $neto = (float) ($datos['neto'] ?? max(0, $netoPrevio - $descuento));
        $iva = (float) ($datos['iva'] ?? 0);
        $total = (float) ($datos['total'] ?? ($neto + $iva));
        $numero = trim((string) $datos['factura_numero']);
        $concepto = trim((string) ($datos['concepto'] ?? '')) ?: "Factura N° {$numero}";

        return MovimientoCuenta::create([
            'titular_type' => $proveedor->getMorphClass(),
            'titular_id' => $proveedor->id,
            'tipo' => 'factura',
            'concepto' => $concepto,
            'importe' => round($total, 2),
            'factura_numero' => $numero,
            'factura_neto' => round($neto, 2),
            'factura_iva' => round($iva, 2),
            'user_id' => $user->id,
            'fecha' => $datos['fecha'],
        ]);
    }

    public function registrarPago(Proveedor $proveedor, User $user, array $datos): MovimientoCuenta
    {
        return DB::transaction(function () use ($proveedor, $user, $datos) {
            $empresa = $proveedor->empresa ?? $proveedor->empresa()->firstOrFail();
            $conRetencion = ! empty($datos['aplicar_retencion']) && $empresa->agente_retencion_iibb;

            $montoSujeto = (float) ($datos['monto_sujeto'] ?? $datos['importe'] ?? 0);
            $montoRetencion = 0.0;
            $montoNeto = (float) ($datos['monto_neto'] ?? $datos['importe'] ?? 0);

            if ($conRetencion) {
                if ($montoSujeto <= 0) {
                    throw new InvalidArgumentException('Indicá el monto sujeto a retención.');
                }

                $calculo = $this->retenciones->calcular(
                    $montoSujeto,
                    (float) $datos['alicuota'],
                    isset($datos['monto_retencion']) ? (float) $datos['monto_retencion'] : null,
                );
                $montoRetencion = $calculo['monto'];
                $montoNeto = $datos['monto_neto'] ?? $calculo['neto'];
                $importeCtaCte = $montoNeto + $montoRetencion;
            } else {
                $importeCtaCte = $montoNeto > 0 ? $montoNeto : $montoSujeto;
            }

            if ($importeCtaCte <= 0) {
                throw new InvalidArgumentException('El importe del pago debe ser mayor a cero.');
            }

            $concepto = trim((string) ($datos['concepto'] ?? '')) ?: 'Pago cuenta corriente proveedor';

            $medioPagoId = $datos['medio_pago_id'] ?? null;
            $bonificacion = (bool) ($datos['bonificacion'] ?? false);
            $cajaSesionId = null;

            if (! $bonificacion && $medioPagoId && $montoNeto > 0) {
                $cajaSesionId = $this->resolverCajaSesion($user)?->id;
            }

            $movimiento = MovimientoCuenta::create([
                'titular_type' => $proveedor->getMorphClass(),
                'titular_id' => $proveedor->id,
                'tipo' => 'pago',
                'concepto' => $concepto,
                'importe' => -round($importeCtaCte, 2),
                'factura_numero' => $conRetencion ? ($datos['factura_numero'] ?? null) : null,
                'factura_neto' => $conRetencion ? round($montoSujeto, 2) : null,
                'medio_pago_id' => $bonificacion ? null : $medioPagoId,
                'caja_sesion_id' => $cajaSesionId,
                'user_id' => $user->id,
                'fecha' => $datos['fecha'],
            ]);

            if ($conRetencion) {
                $retencion = $this->retenciones->crearDesdePago($proveedor, $movimiento, $user, [
                    'factura_numero' => $datos['factura_numero'] ?? '0',
                    'monto_sujeto' => $montoSujeto,
                    'alicuota' => (float) $datos['alicuota'],
                    'monto' => $montoRetencion,
                    'fecha' => $datos['fecha_retencion'] ?? $datos['fecha'],
                    'regimen' => $datos['regimen'] ?? null,
                    'jurisdiccion' => $datos['jurisdiccion'] ?? null,
                ]);

                $movimiento->update([
                    'concepto' => $concepto.' — Ret. recibo N° '.$retencion->numero_recibo,
                ]);
            }

            if ($cajaSesionId && $montoNeto > 0 && ! $bonificacion) {
                CajaMovimiento::create([
                    'caja_sesion_id' => $cajaSesionId,
                    'user_id' => $user->id,
                    'tipo' => 'egreso',
                    'concepto' => $movimiento->concepto,
                    'importe' => round($montoNeto, 2),
                ]);
            }

            return $movimiento;
        });
    }

    private function resolverCajaSesion(User $user): ?CajaSesion
    {
        if (! $user->sucursal_id) {
            return null;
        }

        return CajaSesion::query()
            ->where('estado', 'abierta')
            ->whereHas('caja', fn ($q) => $q
                ->where('sucursal_id', $user->sucursal_id)
                ->where('activa', true))
            ->latest('abierta_at')
            ->first();
    }
}
