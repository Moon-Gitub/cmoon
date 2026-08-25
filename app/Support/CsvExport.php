<?php

namespace App\Support;

use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvExport
{
    /**
     * @param  iterable<int, array<int|string, scalar|null>>  $filas
     * @param  list<string>  $encabezados
     */
    public static function download(string $filename, array $encabezados, iterable $filas): StreamedResponse
    {
        return response()->streamDownload(function () use ($encabezados, $filas) {
            $salida = fopen('php://output', 'w');
            fwrite($salida, "\xEF\xBB\xBF");
            fputcsv($salida, $encabezados, ';');
            foreach ($filas as $fila) {
                fputcsv($salida, array_values($fila), ';');
            }
            fclose($salida);
        }, $filename, ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    public static function money(float $n): string
    {
        return number_format($n, 2, ',', '');
    }

    public static function qty(float $n): string
    {
        return rtrim(rtrim(number_format($n, 3, ',', ''), '0'), ',');
    }
}
