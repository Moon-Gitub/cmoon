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
