<?php

namespace App\Services\Afip;

use App\Models\Emisor;
use RuntimeException;
use Throwable;

/**
 * Consulta padrón AFIP (ws_sr_padron_a5) para autocompletar clientes por CUIT.
 *
 * Requiere que el certificado del emisor tenga autorizado el servicio
 * ws_sr_padron_a5 en AFIP. Si no, el error se muestra claro al usuario.
 *
 * TODO: cachear respuestas, padrón A13, mapear más campos de domicilio.
 */
class PadronAfipService
{
    public function consultarPorCuit(string $cuit, ?Emisor $emisor = null): array
    {
        $cuit = preg_replace('/\D/', '', $cuit) ?? '';
        if (strlen($cuit) !== 11) {
            throw new RuntimeException('El CUIT debe tener 11 dígitos.');
        }

        $emisor ??= Emisor::where('activo', true)->orderBy('id')->first();
        if (! $emisor) {
            throw new RuntimeException('No hay emisor AFIP activo para consultar el padrón.');
        }

        try {
            $ticket = app(WsaaService::class)->credenciales($emisor, 'ws_sr_padron_a5');
        } catch (Throwable $e) {
            throw new RuntimeException(
                'Padrón AFIP no disponible. Autorizá ws_sr_padron_a5 en AFIP para el certificado '
                .'o cargá el cliente a mano. Detalle: '.$e->getMessage()
            );
        }

        $produccion = $emisor->esProduccion();
        $wsdl = $produccion
            ? 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5?wsdl'
            : 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5?wsdl';
        $location = $produccion
            ? 'https://aws.afip.gov.ar/sr-padron/webservices/personaServiceA5'
            : 'https://awshomo.afip.gov.ar/sr-padron/webservices/personaServiceA5';

        try {
            $client = AfipSoap::client($wsdl, $location);
            $respuesta = $client->getPersona_v2([
                'token' => $ticket['token'],
                'sign' => $ticket['sign'],
                'cuitRepresentada' => (int) preg_replace('/\D/', '', $emisor->cuit),
                'idPersona' => (int) $cuit,
            ]);
        } catch (Throwable $e) {
            throw new RuntimeException('Error al consultar padrón AFIP: '.$e->getMessage());
        }

        $persona = json_decode(json_encode($respuesta->personaReturn ?? $respuesta ?? []), true);

        return $this->mapearPersona($cuit, is_array($persona) ? $persona : []);
    }

    private function mapearPersona(string $cuit, array $persona): array
    {
        $datos = $persona['datosGenerales'] ?? $persona['persona'] ?? $persona;
        $razon = $datos['razonSocial']
            ?? trim(($datos['apellido'] ?? '').' '.($datos['nombre'] ?? ''));
        $domicilio = $datos['domicilioFiscal'] ?? ($datos['domicilio'][0] ?? null);
        $dir = is_array($domicilio)
            ? trim(($domicilio['direccion'] ?? '').' '.($domicilio['localidad'] ?? ''))
            : null;

        $impuestos = collect($persona['datosMonotributo']['impuesto'] ?? $persona['datosRegimenGeneral']['impuesto'] ?? [])
            ->map(fn ($i) => is_array($i) ? ($i['idImpuesto'] ?? null) : $i)
            ->filter()
            ->all();

        $condicion = 'CONSUMIDOR_FINAL';
        if (! empty($persona['datosMonotributo'])) {
            $condicion = 'MONOTRIBUTO';
        } elseif (in_array(30, $impuestos, false) || ($datos['tipoClave'] ?? '') === 'CUIT') {
            $condicion = 'RESPONSABLE_INSCRIPTO';
        }

        return [
            'cuit' => $cuit,
            'nombre' => $razon !== '' ? $razon : 'CUIT '.$cuit,
            'tipo_documento' => 'CUIT',
            'documento' => $cuit,
            'condicion_iva' => $condicion,
            'domicilio' => $dir ?: null,
            'localidad' => is_array($domicilio) ? ($domicilio['localidad'] ?? null) : null,
        ];
    }
}
