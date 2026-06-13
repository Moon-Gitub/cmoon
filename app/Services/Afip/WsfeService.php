<?php

namespace App\Services\Afip;

use App\Models\Comprobante;
use App\Models\Emisor;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SoapClient;

/**
 * WSFEv1: facturación electrónica. Solicita el CAE para un comprobante
 * ya persistido en estado pendiente.
 */
class WsfeService
{
    private const URL_HOMOLOGACION = 'https://wswhomo.afip.gov.ar/wsfev1/service.asmx';
    private const URL_PRODUCCION = 'https://servicios1.afip.gov.ar/wsfev1/service.asmx';

    /** Código AFIP de alícuotas de IVA */
    private const ALICUOTAS = [
        '0.00' => 3,
        '10.50' => 4,
        '21.00' => 5,
        '27.00' => 6,
        '5.00' => 8,
        '2.50' => 9,
    ];

    public function __construct(private WsaaService $wsaa) {}

    /**
     * Solicita el CAE y actualiza el comprobante. Devuelve el comprobante actualizado.
     */
    public function autorizar(Comprobante $comprobante): Comprobante
    {
        $emisor = $comprobante->emisor;
        $credenciales = $this->wsaa->credenciales($emisor);

        $auth = [
            'Token' => $credenciales['token'],
            'Sign' => $credenciales['sign'],
            'Cuit' => preg_replace('/\D/', '', $emisor->cuit),
        ];

        $cliente = $this->cliente($emisor);
        $puntoVenta = $comprobante->puntoVenta->numero;
        $tipo = $comprobante->tipo_comprobante;

        // Próximo número según AFIP (fuente de verdad)
        $ultimo = $cliente->FECompUltimoAutorizado([
            'Auth' => $auth,
            'PtoVta' => $puntoVenta,
            'CbteTipo' => $tipo,
        ]);

        if (isset($ultimo->FECompUltimoAutorizadoResult->Errors)) {
            $err = $ultimo->FECompUltimoAutorizadoResult->Errors->Err;
            $err = is_array($err) ? $err[0] : $err;
            throw new RuntimeException("AFIP ({$err->Code}): {$err->Msg}");
        }

        $numero = (int) $ultimo->FECompUltimoAutorizadoResult->CbteNro + 1;

        $esC = in_array($tipo, [11, 12, 13], true);
        $neto = $esC ? (float) $comprobante->total : (float) $comprobante->neto;
        $iva = $esC ? 0.0 : (float) $comprobante->iva;

        $detalle = [
            'Concepto' => 1, // Productos
            'DocTipo' => $comprobante->doc_tipo,
            'DocNro' => (float) preg_replace('/\D/', '', $comprobante->doc_numero ?: '0'),
            'CbteDesde' => $numero,
            'CbteHasta' => $numero,
            'CbteFch' => $comprobante->fecha_emision->format('Ymd'),
            'ImpTotal' => round((float) $comprobante->total, 2),
            'ImpTotConc' => round((float) $comprobante->no_gravado, 2),
            'ImpNeto' => round($neto, 2),
            'ImpOpEx' => round((float) $comprobante->exento, 2),
            'ImpIVA' => round($iva, 2),
            'ImpTrib' => 0,
            'MonId' => 'PES',
            'MonCotiz' => 1,
        ];

        // Condición IVA del receptor (obligatorio desde RG 5616)
        $detalle['CondicionIVAReceptorId'] = $this->condicionIvaReceptor($comprobante);

        // Notas de crédito/débito: referencia al comprobante original
        if ($comprobante->comprobante_asociado_id && $comprobante->comprobanteAsociado) {
            $original = $comprobante->comprobanteAsociado;
            $detalle['CbtesAsoc'] = [
                'CbteAsoc' => [[
                    'Tipo' => $original->tipo_comprobante,
                    'PtoVta' => $original->puntoVenta->numero,
                    'Nro' => (int) $original->numero,
                    'Cuit' => preg_replace('/\D/', '', $emisor->cuit),
                    'CbteFch' => $original->fecha_emision->format('Ymd'),
                ]],
            ];
        }

        // Desglose de IVA solo para A y B
        if (! $esC && $comprobante->detalle_iva) {
            $detalle['Iva'] = [
                'AlicIva' => collect($comprobante->detalle_iva)->map(fn ($fila) => [
                    'Id' => self::ALICUOTAS[number_format((float) $fila['alicuota'], 2, '.', '')] ?? 5,
                    'BaseImp' => round((float) $fila['neto'], 2),
                    'Importe' => round((float) $fila['iva'], 2),
                ])->values()->all(),
            ];
        }

        $respuesta = $cliente->FECAESolicitar([
            'Auth' => $auth,
            'FeCAEReq' => [
                'FeCabReq' => [
                    'CantReg' => 1,
                    'PtoVta' => $puntoVenta,
                    'CbteTipo' => $tipo,
                ],
                'FeDetReq' => ['FECAEDetRequest' => $detalle],
            ],
        ]);

        return $this->procesarRespuesta($comprobante, $respuesta, $numero);
    }

    private function procesarRespuesta(Comprobante $comprobante, object $respuesta, int $numero): Comprobante
    {
        $resultado = $respuesta->FECAESolicitarResult;
        $json = json_decode(json_encode($resultado), true);

        if (isset($resultado->Errors)) {
            $err = $resultado->Errors->Err;
            $err = is_array($err) ? $err[0] : $err;

            $comprobante->update([
                'estado' => 'error',
                'mensaje_afip' => "({$err->Code}) {$err->Msg}",
                'respuesta_afip' => $json,
            ]);

            return $comprobante;
        }

        $det = $resultado->FeDetResp->FECAEDetResponse;
        $det = is_array($det) ? $det[0] : $det;

        if ($det->Resultado === 'A') {
            $comprobante->update([
                'numero' => $numero,
                'cae' => $det->CAE,
                'cae_vencimiento' => \Carbon\Carbon::createFromFormat('Ymd', $det->CAEFchVto),
                'estado' => 'autorizado',
                'mensaje_afip' => null,
                'respuesta_afip' => $json,
            ]);
        } else {
            $observaciones = collect(json_decode(json_encode($det->Observaciones->Obs ?? []), true))
                ->map(fn ($o) => is_array($o) ? "({$o['Code']}) {$o['Msg']}" : '')
                ->filter()
                ->implode(' | ');

            $comprobante->update([
                'estado' => 'rechazado',
                'mensaje_afip' => $observaciones ?: 'Rechazado por AFIP sin detalle.',
                'respuesta_afip' => $json,
            ]);
        }

        return $comprobante;
    }

    private function condicionIvaReceptor(Comprobante $comprobante): int
    {
        return match ($comprobante->receptor_condicion_iva) {
            'RESPONSABLE_INSCRIPTO' => 1,
            'EXENTO' => 4,
            'MONOTRIBUTO' => 6,
            default => 5, // Consumidor final
        };
    }

    private function cliente(Emisor $emisor): SoapClient
    {
        return new SoapClient(resource_path('afip/wsdl/wsfe.wsdl'), [
            'soap_version' => SOAP_1_2,
            'location' => $emisor->esProduccion() ? self::URL_PRODUCCION : self::URL_HOMOLOGACION,
            'trace' => 1,
            'exceptions' => true,
            'connection_timeout' => 30,
        ]);
    }
}
