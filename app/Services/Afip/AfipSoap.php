<?php

namespace App\Services\Afip;

use RuntimeException;
use SoapClient;

/**
 * Opciones comunes de SoapClient para WSAA/WSFE.
 * Fuerza IPv4 y, si hace falta, conecta por IP con SNI (peer_name) para
 * evitar fallos de DNS/egress típicos en Docker/VPS.
 */
class AfipSoap
{
    public static function client(string $wsdlPath, string $location): SoapClient
    {
        [$connectUrl, $peerName] = self::resolverLocation($location);

        return new SoapClient($wsdlPath, [
            'soap_version' => SOAP_1_2,
            'location' => $connectUrl,
            'encoding' => 'UTF-8',
            'trace' => 1,
            'exceptions' => true,
            'connection_timeout' => 45,
            'cache_wsdl' => WSDL_CACHE_DISK,
            'stream_context' => stream_context_create([
                'socket' => [
                    'bindto' => '0.0.0.0:0',
                ],
                'ssl' => [
                    'verify_peer' => true,
                    'verify_peer_name' => true,
                    'peer_name' => $peerName,
                    'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                ],
                'http' => [
                    'timeout' => 45,
                    'header' => "Host: {$peerName}\r\n",
                ],
            ]),
        ]);
    }

    /**
     * @return array{0: string, 1: string} [url para conectar, hostname TLS]
     */
    private static function resolverLocation(string $location): array
    {
        $parts = parse_url($location);
        $host = $parts['host'] ?? null;
        if (! $host) {
            throw new RuntimeException("URL AFIP inválida: {$location}");
        }

        $ip = gethostbyname($host);
        if ($ip === $host || ! filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4)) {
            // Dejar hostname; el fallo se verá en el SOAP
            return [$location, $host];
        }

        $scheme = $parts['scheme'] ?? 'https';
        $port = isset($parts['port']) ? ':'.$parts['port'] : '';
        $path = ($parts['path'] ?? '/').(isset($parts['query']) ? '?'.$parts['query'] : '');

        return ["{$scheme}://{$ip}{$port}{$path}", $host];
    }

    /**
     * @return list<array{host: string, ip: string|null, ok: bool, detail: string}>
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
            $ip = gethostbyname($host);
            $ipOk = $ip !== $host && filter_var($ip, FILTER_VALIDATE_IP);

            $errno = 0;
            $errstr = '';
            $target = $ipOk ? $ip : $host;
            $fp = @stream_socket_client(
                'ssl://'.$target.':443',
                $errno,
                $errstr,
                12,
                STREAM_CLIENT_CONNECT,
                stream_context_create([
                    'socket' => ['bindto' => '0.0.0.0:0'],
                    'ssl' => [
                        'verify_peer' => true,
                        'verify_peer_name' => true,
                        'peer_name' => $host,
                        'crypto_method' => STREAM_CRYPTO_METHOD_TLSv1_2_CLIENT,
                    ],
                ])
            );

            if ($fp) {
                fclose($fp);
                $out[] = [
                    'host' => $host,
                    'ip' => $ipOk ? $ip : null,
                    'ok' => true,
                    'detail' => 'TCP/TLS 443 OK',
                ];
            } else {
                $out[] = [
                    'host' => $host,
                    'ip' => $ipOk ? $ip : null,
                    'ok' => false,
                    'detail' => trim("{$errno} {$errstr}") ?: 'falló stream_socket_client',
                ];
            }
        }

        return $out;
    }
}
