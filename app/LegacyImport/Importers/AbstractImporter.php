<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;

abstract class AbstractImporter implements LegacyImporterInterface
{
    protected function parseDate(mixed $value): ?string
    {
        if ($value === null || $value === '' || $value === '0000-00-00') {
            return null;
        }

        $value = trim((string) $value);

        foreach (['d/m/Y', 'd-m-Y', 'Y-m-d', 'd/m/Y H:i:s', 'Y-m-d H:i:s'] as $fmt) {
            try {
                $dt = \Carbon\Carbon::createFromFormat($fmt, $value);
                if ($dt !== false) {
                    return $dt->toDateString();
                }
            } catch (\Throwable) {
            }
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateString();
        } catch (\Throwable) {
            return null;
        }
    }

    protected function parseDateTime(mixed $value): ?string
    {
        if ($value === null || $value === '') {
            return null;
        }

        try {
            return \Carbon\Carbon::parse($value)->toDateTimeString();
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Normaliza CUIT/CUIL legacy a varchar(13): dígitos y guiones, recortado.
     * Datos basura (p.ej. 16 dígitos) se truncan para no tumbar el import.
     */
    protected function sanitizeCuit(mixed $value, int $maxLen = 13): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim((string) $value);
        if ($raw === '' || $raw === '0' || $raw === '00000000000') {
            return null;
        }

        // Conservar formato con guiones si cabe; si no, solo dígitos.
        $clean = preg_replace('/[^0-9\-]/', '', $raw) ?? '';
        if ($clean === '') {
            return null;
        }

        if (strlen($clean) > $maxLen) {
            $digits = preg_replace('/\D/', '', $clean) ?? '';
            $clean = $digits !== '' ? $digits : $clean;
        }

        if ($clean === '') {
            return null;
        }

        return substr($clean, 0, $maxLen);
    }

    protected function sanitizeDocumento(mixed $value, int $maxLen = 20): ?string
    {
        if ($value === null) {
            return null;
        }

        $raw = trim(strip_tags((string) $value));
        if ($raw === '' || $raw === '0') {
            return null;
        }

        return mb_substr($raw, 0, $maxLen);
    }

    protected function skipIfMapped(LegacyImportContext $ctx, string $entity, int|string $legacyId): ?int
    {
        return $ctx->mappedOrSkip($entity, $legacyId);
    }

    protected function tableExists(LegacyImportContext $ctx, string $table): bool
    {
        return $ctx->legacy('information_schema.tables')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->exists();
    }

    protected function columnExists(LegacyImportContext $ctx, string $table, string $column): bool
    {
        return $ctx->legacy('information_schema.columns')
            ->where('TABLE_SCHEMA', config('database.connections.'.config('legacy.connection').'.database'))
            ->where('TABLE_NAME', $table)
            ->where('COLUMN_NAME', $column)
            ->exists();
    }
}
