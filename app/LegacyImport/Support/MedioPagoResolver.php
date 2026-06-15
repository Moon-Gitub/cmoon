<?php

namespace App\LegacyImport\Support;

use App\Models\MedioPago;
use Illuminate\Support\Collection;

class MedioPagoResolver
{
    /** @var Collection<int, MedioPago>|null */
    private static ?Collection $cache = null;

    public static function resolve(int $empresaId, string $tipoLegacy): ?int
    {
        self::$cache ??= MedioPago::where('empresa_id', $empresaId)->get();

        $needle = strtolower(trim($tipoLegacy));

        $match = self::$cache->first(function (MedioPago $m) use ($needle) {
            $nombre = strtolower($m->nombre);

            return $nombre === $needle
                || str_contains($nombre, $needle)
                || str_contains($needle, $nombre);
        });

        if ($match) {
            return $match->id;
        }

        $efectivo = self::$cache->first(fn (MedioPago $m) => $m->tipo === 'efectivo');

        return $efectivo?->id ?? self::$cache->first()?->id;
    }

    public static function reset(): void
    {
        self::$cache = null;
    }
}
