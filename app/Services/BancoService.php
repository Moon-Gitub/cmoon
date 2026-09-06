<?php

namespace App\Services;

use App\Models\CuentaBancaria;
use App\Models\MovimientoBancario;
use Carbon\Carbon;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class BancoService
{
    /**
     * @return list<MovimientoBancario>
     */
    public function importarCsv(CuentaBancaria $cuenta, string|UploadedFile $path): array
    {
        $filePath = $path instanceof UploadedFile ? $path->getRealPath() : $path;
        if (! $filePath || ! is_readable($filePath)) {
            throw new InvalidArgumentException('No se pudo leer el archivo CSV.');
        }

        $contenido = file_get_contents($filePath);
        if ($contenido === false || trim($contenido) === '') {
            throw new InvalidArgumentException('El CSV está vacío.');
        }

        $lineas = preg_split('/\r\n|\r|\n/', $contenido) ?: [];
        $creados = [];

        DB::transaction(function () use ($cuenta, $lineas, &$creados) {
            foreach ($lineas as $i => $linea) {
                $linea = trim($linea);
                if ($linea === '' || $i === 0 && preg_match('/^fecha/i', $linea)) {
                    continue;
                }

                $parts = str_getcsv($linea, ';');
                if (count($parts) < 3) {
                    $parts = str_getcsv($linea, ',');
                }
                if (count($parts) < 3) {
                    continue;
                }

                [$fechaRaw, $concepto, $importeRaw] = array_map('trim', array_slice($parts, 0, 3));
                $fecha = $this->parseFecha($fechaRaw);
                $importe = $this->parseImporte($importeRaw);
                if (! $fecha || $importe === null) {
                    continue;
                }

                $creados[] = MovimientoBancario::create([
                    'cuenta_bancaria_id' => $cuenta->id,
                    'fecha' => $fecha->toDateString(),
                    'concepto' => $concepto !== '' ? $concepto : 'Movimiento importado',
                    'importe' => $importe,
                    'saldo' => null,
                    'referencia_externa' => null,
                    'conciliado' => false,
                ]);
            }
        });

        return $creados;
    }

    private function parseFecha(string $raw): ?Carbon
    {
        $raw = trim($raw);
        foreach (['d/m/Y', 'Y-m-d', 'd-m-Y', 'd/m/y'] as $fmt) {
            try {
                $d = Carbon::createFromFormat($fmt, $raw);

                return $d ?: null;
            } catch (\Throwable) {
                continue;
            }
        }

        try {
            return Carbon::parse($raw);
        } catch (\Throwable) {
            return null;
        }
    }

    private function parseImporte(string $raw): ?float
    {
        $raw = trim(str_replace(['$', ' '], '', $raw));
        if ($raw === '') {
            return null;
        }

        if (str_contains($raw, ',') && str_contains($raw, '.')) {
            $raw = str_replace('.', '', $raw);
            $raw = str_replace(',', '.', $raw);
        } elseif (str_contains($raw, ',')) {
            $raw = str_replace(',', '.', $raw);
        }

        if (! is_numeric($raw)) {
            return null;
        }

        return round((float) $raw, 2);
    }
}
