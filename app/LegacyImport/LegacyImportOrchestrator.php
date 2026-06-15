<?php

namespace App\LegacyImport;

use App\LegacyImport\Importers\LegacyImporterInterface;
use App\LegacyImport\Support\ImportStats;
use App\LegacyImport\Support\LegacyImportContext;
use App\Models\Empresa;
use Illuminate\Console\OutputStyle;

class LegacyImportOrchestrator
{
    /** @param list<string>|null $onlyKeys */
    public function run(
        LegacyImportContext $ctx,
        ?array $onlyKeys = null,
        ?OutputStyle $output = null,
    ): ImportStats {
        $importers = $this->resolveImporters($onlyKeys);

        foreach ($importers as $importer) {
            $output?->writeln("<info>→ {$importer->label()}</info> ({$importer->key()})");

            try {
                $importer->import($ctx);
            } catch (\Throwable $e) {
                $output?->error("Error en {$importer->key()}: {$e->getMessage()}");
                throw $e;
            }
        }

        return $ctx->stats;
    }

    /** @return list<LegacyImporterInterface> */
    private function resolveImporters(?array $onlyKeys): array
    {
        $map = config('legacy.importers', []);
        $instances = [];

        foreach ($map as $key => $class) {
            if ($onlyKeys !== null && ! in_array($key, $onlyKeys, true)) {
                continue;
            }

            $importer = app($class);
            if (! $importer instanceof LegacyImporterInterface) {
                continue;
            }

            $instances[] = $importer;
        }

        return $instances;
    }

    public static function resolveEmpresaId(?int $empresaId, bool $createEmpresa, int $legacyEmpresaId): int
    {
        if ($createEmpresa) {
            $legacy = \Illuminate\Support\Facades\DB::connection(config('legacy.connection'))
                ->table('empresa')
                ->where('id', $legacyEmpresaId)
                ->first();

            if (! $legacy) {
                throw new \RuntimeException("Empresa legacy {$legacyEmpresaId} no encontrada.");
            }

            return \App\LegacyImport\Importers\SetupImporter::createEmpresaFromLegacy($legacy)->id;
        }

        if (! $empresaId) {
            throw new \RuntimeException('Indicá --empresa-id=N o --create-empresa.');
        }

        Empresa::findOrFail($empresaId);

        return $empresaId;
    }
}
