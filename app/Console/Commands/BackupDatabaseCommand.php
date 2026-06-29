<?php

namespace App\Console\Commands;

use App\Models\AuditLog;
use Carbon\Carbon;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Str;
use Symfony\Component\Process\Process;

class BackupDatabaseCommand extends Command
{
    protected $signature = 'security:backup-db
                            {--compress : Save backup as .gz}
                            {--retention-days= : Override retention window in days}
                            {--path= : Override relative backup path under storage/app}';

    protected $description = 'Create secure DB backup and prune old snapshots.';

    public function handle(): int
    {
        $connectionName = Config::get('database.default');
        $connection = Config::get("database.connections.{$connectionName}", []);
        $driver = $connection['driver'] ?? null;

        if (!in_array($driver, ['mysql', 'pgsql', 'sqlite'], true)) {
            $this->error("Unsupported database driver for backup: {$driver}");
            return self::FAILURE;
        }

        $relativePath = trim((string) ($this->option('path') ?: config('security.backup.path', 'backups/database')), '/');
        $backupDir = storage_path('app/' . $relativePath);

        if (!File::exists($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Ymd_His');
        $databaseName = $connection['database'] ?? 'database';
        $baseFilename = sprintf('%s_%s.sql', $databaseName, $timestamp);
        $sqlPath = $backupDir . DIRECTORY_SEPARATOR . $baseFilename;

        $result = $this->dumpDatabase($driver, $connectionName, $connection, $sqlPath);
        if ($result !== true) {
            $this->error($result);
            $this->recordAudit('security:backup-db-failed', [
                'driver' => $driver,
                'error' => $result,
            ]);

            return self::FAILURE;
        }

        $finalPath = $sqlPath;
        if ((bool) $this->option('compress')) {
            $gzPath = $sqlPath . '.gz';
            $compressed = gzencode((string) file_get_contents($sqlPath), 9);
            if ($compressed === false) {
                $this->error('Backup created, but compression failed.');
                return self::FAILURE;
            }

            file_put_contents($gzPath, $compressed);
            @unlink($sqlPath);
            $finalPath = $gzPath;
        }

        $retentionDays = (int) ($this->option('retention-days') ?: config('security.backup.retention_days', 14));
        $pruned = $this->pruneOldBackups($backupDir, $retentionDays);

        $this->info('Database backup completed: ' . $finalPath);
        $this->line('Old backups removed: ' . $pruned);

        $this->recordAudit('security:backup-db', [
            'driver' => $driver,
            'file' => str_replace(storage_path('app') . DIRECTORY_SEPARATOR, '', $finalPath),
            'retention_days' => $retentionDays,
            'pruned' => $pruned,
        ]);

        return self::SUCCESS;
    }

    private function dumpDatabase(string $driver, string $connectionName, array $connection, string $targetPath)
    {
        if ($driver === 'sqlite') {
            $sqliteFile = $connection['database'] ?? null;
            if (!$sqliteFile || !file_exists($sqliteFile)) {
                return 'SQLite file does not exist.';
            }

            if (!copy($sqliteFile, $targetPath)) {
                return 'Unable to copy SQLite database file.';
            }

            return true;
        }

        if ($driver === 'mysql') {
            return $this->dumpMySql($connectionName, $connection, $targetPath);
        }

        return $this->dumpPostgres($connection, $targetPath);
    }

    private function dumpMySql(string $connectionName, array $connection, string $targetPath)
    {
        $binary = $this->resolveMySqlDumpBinary();

        $arguments = [
            $binary,
            '--host=' . ($connection['host'] ?? '127.0.0.1'),
            '--port=' . ($connection['port'] ?? 3306),
            '--user=' . ($connection['username'] ?? ''),
            '--single-transaction',
            '--quick',
            '--skip-lock-tables',
            (string) ($connection['database'] ?? ''),
        ];

        $process = new Process($arguments, base_path(), [
            'MYSQL_PWD' => (string) ($connection['password'] ?? ''),
        ]);

        $result = $this->runDumpProcess($process, $targetPath, (int) config('security.backup.timeout_seconds', 300));

        if ($result === true) {
            return true;
        }

        // Fallback for environments where mysqldump fails (e.g. Windows TCP socket provider issues).
        $fallbackResult = $this->dumpMySqlViaPdo($connectionName, $connection, $targetPath);
        if ($fallbackResult === true) {
            return true;
        }

        return $result . ' | PDO fallback failed: ' . $fallbackResult;
    }

    private function dumpMySqlViaPdo(string $connectionName, array $connection, string $targetPath)
    {
        try {
            $database = (string) ($connection['database'] ?? 'database');
            $db = DB::connection($connectionName);
            $pdo = $db->getPdo();

            $lines = [];
            $lines[] = '-- SQL backup generated by Laravel fallback';
            $lines[] = '-- Database: ' . $database;
            $lines[] = '-- Generated at: ' . now()->toDateTimeString();
            $lines[] = 'SET FOREIGN_KEY_CHECKS=0;';
            $lines[] = '';

            $tableRows = $db->select('SHOW FULL TABLES WHERE Table_type = "BASE TABLE"');

            foreach ($tableRows as $row) {
                $rowArray = (array) $row;
                $table = (string) reset($rowArray);

                $createStmtRow = $db->selectOne('SHOW CREATE TABLE `' . str_replace('`', '``', $table) . '`');
                $createStmt = (array) $createStmtRow;
                $createSql = (string) ($createStmt['Create Table'] ?? '');

                $lines[] = 'DROP TABLE IF EXISTS `' . $table . '`;';
                $lines[] = $createSql . ';';

                $records = $db->table($table)->get();
                foreach ($records as $record) {
                    $recordArray = (array) $record;
                    $columns = array_map(static function ($column) {
                        return '`' . str_replace('`', '``', (string) $column) . '`';
                    }, array_keys($recordArray));

                    $values = array_map(static function ($value) use ($pdo) {
                        if ($value === null) {
                            return 'NULL';
                        }

                        if (is_bool($value)) {
                            return $value ? '1' : '0';
                        }

                        if (is_int($value) || is_float($value)) {
                            return (string) $value;
                        }

                        return $pdo->quote((string) $value);
                    }, array_values($recordArray));

                    $lines[] = sprintf(
                        'INSERT INTO `%s` (%s) VALUES (%s);',
                        str_replace('`', '``', $table),
                        implode(', ', $columns),
                        implode(', ', $values)
                    );
                }

                $lines[] = '';
            }

            $viewRows = $db->select('SHOW FULL TABLES WHERE Table_type = "VIEW"');
            foreach ($viewRows as $row) {
                $rowArray = (array) $row;
                $view = (string) reset($rowArray);

                $createViewRow = $db->selectOne('SHOW CREATE VIEW `' . str_replace('`', '``', $view) . '`');
                $createView = (array) $createViewRow;
                $createViewSql = (string) ($createView['Create View'] ?? '');

                if ($createViewSql !== '') {
                    $lines[] = 'DROP VIEW IF EXISTS `' . $view . '`;';
                    $lines[] = $createViewSql . ';';
                    $lines[] = '';
                }
            }

            $lines[] = 'SET FOREIGN_KEY_CHECKS=1;';

            $content = implode(PHP_EOL, $lines) . PHP_EOL;

            if (file_put_contents($targetPath, $content) === false) {
                return 'Unable to write fallback SQL backup file.';
            }

            return true;
        } catch (\Throwable $e) {
            return $e->getMessage();
        }
    }

    private function dumpPostgres(array $connection, string $targetPath)
    {
        $binary = config('security.backup.pg_dump_binary', 'pg_dump');

        $arguments = [
            $binary,
            '--host=' . ($connection['host'] ?? '127.0.0.1'),
            '--port=' . ($connection['port'] ?? 5432),
            '--username=' . ($connection['username'] ?? ''),
            '--format=plain',
            '--encoding=UTF8',
            (string) ($connection['database'] ?? ''),
        ];

        $process = new Process($arguments, base_path(), [
            'PGPASSWORD' => (string) ($connection['password'] ?? ''),
        ]);

        return $this->runDumpProcess($process, $targetPath, (int) config('security.backup.timeout_seconds', 300));
    }

    private function runDumpProcess(Process $process, string $targetPath, int $timeoutSeconds)
    {
        $process->setTimeout(max(30, $timeoutSeconds));
        $process->run();

        if (!$process->isSuccessful()) {
            return trim($process->getErrorOutput()) ?: trim($process->getOutput()) ?: 'Database dump command failed.';
        }

        $output = $process->getOutput();
        if ($output === '' && file_exists($targetPath)) {
            return true;
        }

        if ($output === '') {
            return 'Database dump returned empty output.';
        }

        if (file_put_contents($targetPath, $output) === false) {
            return 'Unable to write backup file to disk.';
        }

        return true;
    }

    private function pruneOldBackups(string $backupDir, int $retentionDays): int
    {
        $deleted = 0;
        $retentionDays = max(1, $retentionDays);
        $threshold = Carbon::now()->subDays($retentionDays);

        foreach (File::files($backupDir) as $file) {
            $extension = strtolower($file->getExtension());
            if (!in_array($extension, ['sql', 'gz'], true)) {
                continue;
            }

            if (Carbon::createFromTimestamp($file->getMTime())->lt($threshold)) {
                File::delete($file->getPathname());
                $deleted++;
            }
        }

        return $deleted;
    }

    private function recordAudit(string $description, array $properties): void
    {
        $data = [
            'event_uuid' => (string) \Illuminate\Support\Str::uuid(),
            'action' => $description,
            'description' => $description,
            'user_id' => auth()->id() ?? null,
            'properties' => [
                'event' => $description,
                'payload' => $properties,
                'console' => true,
            ],
            'host' => null,
            'request_method' => 'CLI',
            'request_url' => 'artisan security:backup-db',
        ];

        if (!AuditLog::hasColumn('event_uuid')) {
            unset($data['event_uuid'], $data['action'], $data['request_method'], $data['request_url'], $data['user_agent']);
        }

        AuditLog::createSafely($data);
    }

    private function resolveMySqlDumpBinary(): string
    {
        $configured = (string) config('security.backup.mysql_dump_binary', 'mysqldump');
        if ($configured !== '' && $configured !== 'mysqldump') {
            return $configured;
        }

        $candidates = [
            base_path('../mysql/bin/mysqldump.exe'),
            'C:/xampp/mysql/bin/mysqldump.exe',
            'C:/wamp64/bin/mysql/mysql8.0.31/bin/mysqldump.exe',
            'mysqldump',
        ];

        foreach ($candidates as $candidate) {
            if (Str::endsWith(strtolower($candidate), '.exe') && file_exists($candidate)) {
                return $candidate;
            }

            if ($candidate === 'mysqldump') {
                return $candidate;
            }
        }

        return 'mysqldump';
    }
}
