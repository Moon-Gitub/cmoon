<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;

class LegacyLoadDumpCommand extends Command
{
    protected $signature = 'legacy:load-dump
        {--url= : URL del dump .sql o .sql.gz}
        {--database=jamrod_legacy : Nombre de la BD destino del dump}
        {--force : Reimportar aunque la BD ya tenga tablas}';

    protected $description = 'Descargar dump legacy y cargarlo en MySQL (misma instancia) para legacy:import';

    public function handle(): int
    {
        $url = $this->option('url') ?: env('LEGACY_DUMP_URL');
        $database = (string) $this->option('database');

        if (blank($url)) {
            $this->error('Falta --url o LEGACY_DUMP_URL');

            return self::FAILURE;
        }

        $rootUser = env('DB_ROOT_USERNAME', 'root');
        $rootPass = env('DB_ROOT_PASSWORD') ?: env('DB_PASSWORD');
        $host = env('DB_HOST', 'mysql');
        $port = env('DB_PORT', '3306');

        if (blank($rootPass)) {
            $this->error('Falta DB_ROOT_PASSWORD para crear/cargar la BD legacy');

            return self::FAILURE;
        }

        // ¿Ya cargada?
        try {
            $pdo = $this->rootPdo($host, $port, $rootUser, $rootPass);
            $exists = $pdo->query("SHOW DATABASES LIKE ".$pdo->quote($database))->fetch();
            if ($exists) {
                $pdo->exec("USE `{$database}`");
                $tables = (int) $pdo->query('SELECT COUNT(*) FROM information_schema.tables WHERE table_schema='.$pdo->quote($database))->fetchColumn();
                $productos = 0;
                if ($tables > 0) {
                    try {
                        $productos = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
                    } catch (\Throwable) {
                        $productos = 0;
                    }
                }

                if ($productos > 0 && ! $this->option('force')) {
                    $this->info("BD {$database} ya tiene datos (productos={$productos}). Skip. Usá --force para recrear.");

                    return self::SUCCESS;
                }
            }
        } catch (\Throwable $e) {
            $this->error('No se pudo conectar a MySQL root: '.$e->getMessage());

            return self::FAILURE;
        }

        $tmp = storage_path('app/legacy-dump-'.uniqid().'.sql.gz');
        $tmpSql = preg_replace('/\.gz$/', '', $tmp);
        if (! str_ends_with($tmp, '.gz')) {
            $tmp .= '.gz';
            $tmpSql = substr($tmp, 0, -3);
        }

        $this->info("Descargando dump: {$url}");

        try {
            $response = Http::timeout(300)->withOptions(['sink' => $tmp])->get($url);
            if (! $response->successful()) {
                $this->error('Download HTTP '.$response->status());

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Download falló: '.$e->getMessage());

            return self::FAILURE;
        }

        $size = filesize($tmp) ?: 0;
        $this->line('  Descargado: '.number_format($size / 1024 / 1024, 2).' MB');

        // Detect gzip
        $fh = fopen($tmp, 'rb');
        $magic = $fh ? fread($fh, 2) : '';
        if ($fh) {
            fclose($fh);
        }
        $isGzip = $magic === "\x1f\x8b";

        $sqlFile = $tmp;
        if ($isGzip) {
            $this->info('Descomprimiendo...');
            $result = Process::timeout(300)->run(['gunzip', '-kf', $tmp]);
            if ($result->failed()) {
                // fallback php
                $out = gzdecode(file_get_contents($tmp));
                if ($out === false) {
                    $this->error('No se pudo descomprimir el dump');

                    return self::FAILURE;
                }
                file_put_contents($tmpSql, $out);
            }
            $sqlFile = $tmpSql;
        }

        $this->info("Creando BD {$database}...");
        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        if ($this->option('force')) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
            $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        // Grant app user access
        $appUser = env('DB_USERNAME', 'cmoon');
        try {
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$appUser}'@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
        } catch (\Throwable $e) {
            $this->warn('Grant: '.$e->getMessage());
        }

        $this->info('Importando SQL (puede tardar)...');
        $cmd = sprintf(
            'mysql -h%s -P%s -u%s -p%s %s < %s',
            escapeshellarg($host),
            escapeshellarg((string) $port),
            escapeshellarg($rootUser),
            escapeshellarg($rootPass),
            escapeshellarg($database),
            escapeshellarg($sqlFile),
        );

        $result = Process::timeout(1800)->run(['bash', '-lc', $cmd]);
        @unlink($tmp);
        @unlink($tmpSql);

        if ($result->failed()) {
            $this->error('mysql import falló: '.$result->errorOutput());

            return self::FAILURE;
        }

        $pdo->exec("USE `{$database}`");
        $productos = (int) $pdo->query('SELECT COUNT(*) FROM productos')->fetchColumn();
        $ventas = (int) $pdo->query('SELECT COUNT(*) FROM ventas')->fetchColumn();
        $this->info("OK — productos={$productos}, ventas={$ventas}");

        return self::SUCCESS;
    }

    private function rootPdo(string $host, string $port, string $user, string $pass): \PDO
    {
        return new \PDO(
            "mysql:host={$host};port={$port};charset=utf8mb4",
            $user,
            $pass,
            [\PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION]
        );
    }
}
