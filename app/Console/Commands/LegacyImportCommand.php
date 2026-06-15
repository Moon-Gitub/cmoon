<?php

namespace App\Console\Commands;

use App\LegacyImport\LegacyImportOrchestrator;
use App\LegacyImport\Support\IdMap;
use App\LegacyImport\Support\ImportStats;
use App\LegacyImport\Support\LegacyConnection;
use App\LegacyImport\Support\LegacyImportContext;
use Illuminate\Console\Command;

class LegacyImportCommand extends Command
{
    protected $signature = 'legacy:import
        {--empresa-id= : ID de la empresa destino en CMoon}
        {--create-empresa : Crear empresa nueva desde datos legacy}
        {--legacy-empresa-id=1 : ID empresa en BD legacy (casi siempre 1)}
        {--only= : Importadores separados por coma (setup,productos,ventas,...)}
        {--dry-run : Simular sin escribir en CMoon}
        {--force : Reimportar aunque exista mapeo}
        {--reset-maps : Borrar mapeos previos de esta empresa antes de importar}';

    protected $description = 'Importar datos desde BD legacy (POS Moon / demonew) hacia CMoon';

    public function handle(LegacyImportOrchestrator $orchestrator): int
    {
        LegacyConnection::assertAvailable();

        $legacyEmpresaId = (int) $this->option('legacy-empresa-id');
        $empresaId = LegacyImportOrchestrator::resolveEmpresaId(
            $this->option('empresa-id') ? (int) $this->option('empresa-id') : null,
            (bool) $this->option('create-empresa'),
            $legacyEmpresaId,
        );

        $idMap = new IdMap($empresaId);

        if ($this->option('reset-maps') && ! $this->option('dry-run')) {
            $deleted = $idMap->forgetAll();
            $this->warn("Mapeos eliminados: {$deleted}");
        }

        $only = $this->option('only')
            ? array_map('trim', explode(',', (string) $this->option('only')))
            : null;

        if ($only) {
            $valid = array_keys(config('legacy.importers', []));
            foreach ($only as $key) {
                if (! in_array($key, $valid, true)) {
                    $this->error("Importador desconocido: {$key}. Válidos: ".implode(', ', $valid));

                    return self::FAILURE;
                }
            }
        }

        $ctx = new LegacyImportContext(
            empresaId: $empresaId,
            legacyEmpresaId: $legacyEmpresaId,
            idMap: $idMap,
            stats: new ImportStats,
            dryRun: (bool) $this->option('dry-run'),
            force: (bool) $this->option('force'),
        );

        $this->info('Import legacy → CMoon');
        $this->line("  Empresa destino: {$empresaId}");
        $this->line("  Empresa legacy:  {$legacyEmpresaId}");
        $this->line('  Modo: '.($ctx->dryRun ? 'DRY-RUN' : 'ESCRITURA'));

        try {
            $stats = $orchestrator->run($ctx, $only, $this->output);
        } catch (\Throwable $e) {
            $this->error($e->getMessage());

            return self::FAILURE;
        }

        $this->newLine();
        $this->table(
            ['Entidad', 'Creados', 'Omitidos', 'Errores'],
            collect($stats->all())->map(fn ($row, $entity) => [
                $entity,
                $row['created'] ?? 0,
                $row['skipped'] ?? 0,
                $row['errors'] ?? 0,
            ])->values()->all(),
        );

        $this->info('Importación finalizada.');

        return self::SUCCESS;
    }
}
