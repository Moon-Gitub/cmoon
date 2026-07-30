<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;

/**
 * Diagnóstico / reparación de migrate en deploys sin SSH.
 * Escribe el resultado en storage y también lo imprime.
 */
class LegacyMigrateDebugCommand extends Command
{
    protected $signature = 'legacy:migrate-debug';

    protected $description = 'Ejecutar migrate --force y guardar salida en storage/app/migrate-debug.log';

    public function handle(): int
    {
        $log = storage_path('app/migrate-debug.log');
        $this->info('Running migrate --force...');

        try {
            Artisan::call('migrate', ['--force' => true]);
            $output = Artisan::output();
            file_put_contents($log, "OK\n".$output);
            $this->line($output);

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $msg = $e->getMessage()."\n".$e->getTraceAsString();
            file_put_contents($log, "FAIL\n".$msg);
            $this->error($msg);

            // También a tabla temporal si MySQL responde
            try {
                \DB::statement('CREATE TABLE IF NOT EXISTS _migrate_debug (id INT AUTO_INCREMENT PRIMARY KEY, created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP, message LONGTEXT)');
                \DB::table('_migrate_debug')->insert(['message' => substr($msg, 0, 60000)]);
            } catch (\Throwable) {
            }

            return self::FAILURE;
        }
    }
}
