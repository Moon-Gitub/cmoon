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
}
