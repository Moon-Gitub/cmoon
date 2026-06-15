<?php

namespace App\LegacyImport\Importers;

use App\LegacyImport\Support\LegacyImportContext;

interface LegacyImporterInterface
{
    public function key(): string;

    public function label(): string;

    public function import(LegacyImportContext $ctx): void;
}
