<?php

namespace App\Services\Afip;

use SoapClient;

/**
 * Opciones comunes de SoapClient para WSAA/WSFE.
 * Fuerza IPv4 (bindto) porque en varios VPS/Docker la resolución AAAA de AFIP falla
 * con el error clásico "Could not connect to host".
 */
class AfipSoap
{
    public static function client(string $wsdlPath, string $location): SoapClient
    {
        return new SoapClient($wsdlPath, [
            'soap_version' => SOAP_1_2,
            'location' => $location,
            'encoding' => 'UTF-8',
            'trace' => 1,
            'exceptions' => true,
            'connection_timeout' => 45,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'stream_context' => stream_context_create([
                'socket' => [
                    // Forzar salida IPv4
                    'bindto' => '0.0.0.0:0',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                ],
                'http' => [
                    'timeout' => 45,
                ],
            ]),
        ]);
    }

    /**
     * @return list<array{host: string, ok: bool, detail: string}>
     */
    public static function diagnosticarConectividad(): array
    {
        $hosts = [
            'wsaahomo.afip.gov.ar',
            'wsaa.afip.gov.ar',
            'wswhomo.afip.gov.ar',
            'servicios1.afip.gov.ar',
        ];

        $out = [];
        foreach ($hosts as $host) {
            $errno = 0;
            $errstr = '';
            $fp = @stream_socket_client(
                'ssl://'.$host.':443',
                $errno,
                $errstr,
                10,
                STREAM_CLIENT_CONNECT,
                stream_context_create([
                    'socket' => ['bindto' => '0.0.0.0:0'],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                    ],
                ])
            );

            if ($fp) {
                fclose($fp);
                $out[] = ['host' => $host, 'ok' => true, 'detail' => 'TCP/TLS 443 OK'];
            } else {
                $out[] = ['host' => $host, 'ok' => false, 'detail' => trim("{$errno} {$errstr}") ?: 'sin detalle'];
            }
        }

        return $out;
    }
}
