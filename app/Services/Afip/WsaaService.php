<?php

namespace App\Services\Afip;

use App\Models\Emisor;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use SimpleXMLElement;
use SoapClient;

/**
 * WSAA: autenticación contra AFIP. Genera y cachea el Ticket de Acceso (TA)
 * firmando un TRA con el certificado del emisor (PKCS#7).
 * El TA dura 12 horas; se renueva solo cuando expira.
 */
class WsaaService
{
    private const URL_HOMOLOGACION = 'https://wsaahomo.afip.gov.ar/ws/services/LoginCms';
    private const URL_PRODUCCION = 'https://wsaa.afip.gov.ar/ws/services/LoginCms';
    private const SERVICIO = 'wsfe';

    /**
     * Devuelve ['token' => ..., 'sign' => ...] vigentes para el emisor.
     */
    public function credenciales(Emisor $emisor): array
    {
        $ta = $this->taVigente($emisor);

        if (! $ta) {
            $ta = $this->generarTa($emisor);
        }

        return [
            'token' => (string) $ta->credentials->token,
            'sign' => (string) $ta->credentials->sign,
        ];
    }

    private function rutaTa(Emisor $emisor): string
    {
        return "afip/ta/ta-{$emisor->id}-{$emisor->entorno}.xml";
    }

    private function taVigente(Emisor $emisor): ?SimpleXMLElement
    {
        $ruta = $this->rutaTa($emisor);

        if (! Storage::exists($ruta)) {
            return null;
        }

        $ta = simplexml_load_string(Storage::get($ruta));

        if (! $ta || ! isset($ta->header->expirationTime)) {
            return null;
        }

        // Margen de 10 minutos antes del vencimiento real
        if (now()->addMinutes(10)->gte(\Carbon\Carbon::parse((string) $ta->header->expirationTime))) {
            return null;
        }

        return $ta;
    }

    private function generarTa(Emisor $emisor): SimpleXMLElement
    {
        if (! $emisor->tieneCertificado()) {
            throw new RuntimeException(
                'El emisor no tiene certificado AFIP cargado. Subilo desde Facturación → Emisores.'
            );
        }

        $certPath = Storage::path($emisor->certificado_path);
        $keyPath = Storage::path($emisor->clave_privada_path);

        if (! is_file($certPath) || ! is_file($keyPath)) {
            throw new RuntimeException('No se encontraron los archivos del certificado AFIP en el servidor.');
        }

        $cms = $this->firmarTra($this->crearTra(), $certPath, $keyPath);

        $cliente = new SoapClient(resource_path('afip/wsdl/wsaa.wsdl'), [
            'soap_version' => SOAP_1_2,
            'location' => $emisor->esProduccion() ? self::URL_PRODUCCION : self::URL_HOMOLOGACION,
            'trace' => 1,
            'exceptions' => true,
            'connection_timeout' => 30,
        ]);

        $resultado = $cliente->loginCms(['in0' => $cms]);

        Storage::put($this->rutaTa($emisor), $resultado->loginCmsReturn);

        return simplexml_load_string($resultado->loginCmsReturn);
    }

    private function crearTra(): string
    {
        $tra = new SimpleXMLElement(
            '<?xml version="1.0" encoding="UTF-8"?><loginTicketRequest version="1.0"></loginTicketRequest>'
        );
        $tra->addChild('header');
        $tra->header->addChild('uniqueId', (string) time());
        $tra->header->addChild('generationTime', date('c', time() - 60));
        $tra->header->addChild('expirationTime', date('c', time() + 600));
        $tra->addChild('service', self::SERVICIO);

        $rutaTra = Storage::path('afip/ta/tra-'.uniqid().'.xml');
        $tra->asXML($rutaTra);

        return $rutaTra;
    }

    private function firmarTra(string $rutaTra, string $certPath, string $keyPath): string
    {
        $rutaFirmado = $rutaTra.'.tmp';

        $ok = openssl_pkcs7_sign(
            $rutaTra,
            $rutaFirmado,
            'file://'.$certPath,
            ['file://'.$keyPath, ''],
            [],
            ! PKCS7_DETACHED,
        );

        if (! $ok) {
            @unlink($rutaTra);
            throw new RuntimeException(
                'Error al firmar el TRA. Verificá que el certificado y la clave privada se correspondan.'
            );
        }

        // El CMS es el contenido del MIME sin las primeras 4 líneas de cabecera
        $lineas = file($rutaFirmado);
        $cms = implode('', array_slice($lineas, 4));

        @unlink($rutaTra);
        @unlink($rutaFirmado);

        return $cms;
    }
}
