<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class LegacyLoadDumpCommand extends Command
{
    protected $signature = 'legacy:load-dump
        {--url= : URL del dump .sql o .sql.gz}
        {--database=jamrod_legacy : Nombre de la BD destino del dump}
        {--force : Reimportar aunque la BD ya tenga tablas}';

    protected $description = 'Descargar dump legacy y cargarlo en MySQL (PDO, sin cliente mysql)';

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
        $port = (string) env('DB_PORT', '3306');

        if (blank($rootPass)) {
            $this->error('Falta DB_ROOT_PASSWORD');

            return self::FAILURE;
        }

        try {
            $pdo = $this->pdo($host, $port, $rootUser, $rootPass);
        } catch (\Throwable $e) {
            $this->error('MySQL root: '.$e->getMessage());

            return self::FAILURE;
        }

        $pdo->exec("CREATE DATABASE IF NOT EXISTS `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");

        $productos = $this->countProductos($pdo, $database);
        if ($productos > 0 && ! $this->option('force')) {
            $this->info("BD {$database} ya tiene datos (productos={$productos}). Skip.");

            return self::SUCCESS;
        }

        if ($this->option('force')) {
            $pdo->exec("DROP DATABASE IF EXISTS `{$database}`");
            $pdo->exec("CREATE DATABASE `{$database}` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci");
        }

        $appUser = env('DB_USERNAME', 'cmoon');
        try {
            $pdo->exec("GRANT ALL PRIVILEGES ON `{$database}`.* TO '{$appUser}'@'%'");
            $pdo->exec('FLUSH PRIVILEGES');
        } catch (\Throwable $e) {
            $this->warn('Grant: '.$e->getMessage());
        }

        $this->info("Descargando: {$url}");
        $tmp = storage_path('app/legacy-dump-'.uniqid('', true).'.bin');
        try {
            $response = Http::timeout(600)->withOptions(['sink' => $tmp])->get($url);
            if (! $response->successful()) {
                $this->error('HTTP '.$response->status());

                return self::FAILURE;
            }
        } catch (\Throwable $e) {
            $this->error('Download: '.$e->getMessage());

            return self::FAILURE;
        }

        $raw = file_get_contents($tmp);
        @unlink($tmp);
        if ($raw === false || $raw === '') {
            $this->error('Dump vacío');

            return self::FAILURE;
        }

        if (str_starts_with($raw, "\x1f\x8b")) {
            $this->info('Descomprimiendo gzip...');
            $raw = gzdecode($raw);
            if ($raw === false) {
                $this->error('gzip inválido');

                return self::FAILURE;
            }
        }

        $this->info('Importando SQL ('.number_format(strlen($raw) / 1024 / 1024, 2).' MB)...');

        try {
            $db = $this->pdo($host, $port, $rootUser, $rootPass, $database);
            $db->setAttribute(\PDO::ATTR_EMULATE_PREPARES, true);
            // Import por statements (más seguro que multi-query gigante)
            $this->importSql($db, $raw);
        } catch (\Throwable $e) {
            $this->error('Import: '.$e->getMessage());

            return self::FAILURE;
        }

        $productos = $this->countProductos($pdo, $database);
        $ventas = $this->countTable($pdo, $database, 'ventas');
        $clientes = $this->countTable($pdo, $database, 'clientes');
        $this->info("OK — productos={$productos}, clientes={$clientes}, ventas={$ventas}");

        return self::SUCCESS;
    }

    private function importSql(\PDO $pdo, string $sql): void
    {
        // Quitar comentarios de línea y bloques, respetando strings lo mejor posible de forma simple
        $sql = preg_replace('/^--.*$/m', '', $sql) ?? $sql;
        $sql = preg_replace('/\/\*!.*?\*\//s', '', $sql) ?? $sql;

        $statements = 0;
        $buffer = '';
        $len = strlen($sql);
        $inString = false;
        $stringChar = '';

        for ($i = 0; $i < $len; $i++) {
            $ch = $sql[$i];
            $next = $i + 1 < $len ? $sql[$i + 1] : '';

            if ($inString) {
                $buffer .= $ch;
                if ($ch === '\\' && $next !== '') {
                    $buffer .= $next;
                    $i++;

                    continue;
                }
                if ($ch === $stringChar) {
                    $inString = false;
                }

                continue;
            }

            if ($ch === '\'' || $ch === '"') {
                $inString = true;
                $stringChar = $ch;
                $buffer .= $ch;

                continue;
            }

            if ($ch === ';') {
                $stmt = trim($buffer);
                $buffer = '';
                if ($stmt !== '') {
                    $pdo->exec($stmt);
                    $statements++;
                    if ($statements % 500 === 0) {
                        $this->line("  ... {$statements} statements");
                    }
                }

                continue;
            }

            $buffer .= $ch;
        }

        $stmt = trim($buffer);
        if ($stmt !== '') {
            $pdo->exec($stmt);
            $statements++;
        }

        $this->line("  Statements ejecutados: {$statements}");
    }

    private function pdo(string $host, string $port, string $user, string $pass, ?string $database = null): \PDO
    {
        $dsn = "mysql:host={$host};port={$port};charset=utf8mb4";
        if ($database) {
            $dsn .= ";dbname={$database}";
        }

        return new \PDO($dsn, $user, $pass, [
            \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
            \PDO::MYSQL_ATTR_MULTI_STATEMENTS => true,
        ]);
    }

    private function countProductos(\PDO $pdo, string $database): int
    {
        return $this->countTable($pdo, $database, 'productos');
    }

    private function countTable(\PDO $pdo, string $database, string $table): int
    {
        try {
            $pdo->exec("USE `{$database}`");

            return (int) $pdo->query("SELECT COUNT(*) FROM `{$table}`")->fetchColumn();
        } catch (\Throwable) {
            return 0;
        }
    }
}
