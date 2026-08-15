<?php

namespace App\Services\Backup;

use App\Models\Backup;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Symfony\Component\Process\Process;

class DatabaseBackupEngine
{
    public function __construct(private readonly BackupCompressionService $compression) {}

    /**
     * Create a zipped database dump and return absolute local path.
     */
    public function createDump(Backup $backup, string $tmpDir): string
    {
        if (! is_dir($tmpDir)) {
            mkdir($tmpDir, 0755, true);
        }

        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        $sqlPath = $tmpDir.'/database.sql';

        if (in_array($driver, ['sqlite'], true)) {
            $this->dumpSqlite($connection, $sqlPath);
        } elseif (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->dumpMysql($connection, $tmpDir, $sqlPath);
        } else {
            throw new RuntimeException("محرك قاعدة البيانات غير مدعوم للنسخ: {$driver}");
        }

        $zipPath = $tmpDir.'/database.zip';
        $this->compression->zipFiles(['database.sql' => $sqlPath], $zipPath);
        @unlink($sqlPath);

        return $zipPath;
    }

    public function restoreFromSqlFile(string $sqlPath): void
    {
        $connection = config('database.default');
        $driver = config("database.connections.{$connection}.driver");

        if ($driver === 'sqlite') {
            $this->restoreSqlite($connection, $sqlPath);

            return;
        }

        if (in_array($driver, ['mysql', 'mariadb'], true)) {
            $this->restoreMysql($connection, $sqlPath);

            return;
        }

        throw new RuntimeException("محرك قاعدة البيانات غير مدعوم للاسترجاع: {$driver}");
    }

    /**
     * @return array<int, string>
     */
    public function excludedTables(): array
    {
        return array_values(array_filter(config('backup.exclude_tables', [])));
    }

    private function isExcludedTable(string $table): bool
    {
        return in_array($table, $this->excludedTables(), true);
    }

    private function dumpSqlite(string $connection, string $sqlPath): void
    {
        $pdo = DB::connection($connection)->getPdo();

        // Works for file SQLite and :memory: test databases
        $tables = DB::connection($connection)->select(
            "SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"
        );
        // Avoid BEGIN/COMMIT so restore works inside Laravel test transactions.
        $sql = "-- SQLite logical dump\n";

        foreach ($tables as $table) {
            $name = $table->name;
            if ($this->isExcludedTable($name)) {
                continue;
            }
            $create = DB::connection($connection)->selectOne(
                "SELECT sql FROM sqlite_master WHERE type='table' AND name=?",
                [$name]
            );
            if ($create?->sql) {
                $sql .= "DROP TABLE IF EXISTS \"{$name}\";\n".$create->sql.";\n";
            }

            $rows = DB::connection($connection)->table($name)->get();
            foreach ($rows as $row) {
                $values = array_map(function ($value) use ($pdo) {
                    if ($value === null) {
                        return 'NULL';
                    }
                    if (is_int($value) || is_float($value)) {
                        return (string) $value;
                    }

                    return $pdo->quote((string) $value);
                }, (array) $row);
                $cols = implode(', ', array_map(fn ($c) => '"'.$c.'"', array_keys((array) $row)));
                $sql .= "INSERT INTO \"{$name}\" ({$cols}) VALUES (".implode(', ', $values).");\n";
            }
        }

        if (file_put_contents($sqlPath, $sql) === false) {
            throw new RuntimeException('تعذر كتابة ملف SQL.');
        }
    }

    private function dumpMysql(string $connection, string $tmpDir, string $sqlPath): void
    {
        $binary = $this->resolveMysqldumpBinary();
        $db = config("database.connections.{$connection}");

        if ($binary) {
            $defaultsFile = $tmpDir.'/.my.cnf';
            $this->writeDefaultsFile(
                $defaultsFile,
                (string) ($db['host'] ?? '127.0.0.1'),
                (string) ($db['port'] ?? 3306),
                (string) ($db['username'] ?? ''),
                (string) ($db['password'] ?? ''),
            );

            $databaseName = (string) ($db['database'] ?? '');
            $args = [
                $binary,
                '--defaults-extra-file='.$defaultsFile,
                '--single-transaction',
                '--quick',
                '--routines',
                '--triggers',
                '--hex-blob',
            ];
            foreach ($this->excludedTables() as $table) {
                $args[] = '--ignore-table='.$databaseName.'.'.$table;
            }
            $args[] = $databaseName;

            $process = new Process($args);
            $process->setTimeout((int) config('backup.dump_timeout', 3600));
            $process->setEnv(['TMP' => $tmpDir, 'TEMP' => $tmpDir, 'TMPDIR' => $tmpDir]);

            $out = fopen($sqlPath, 'wb');
            if ($out === false) {
                throw new RuntimeException('تعذر إنشاء ملف dump.');
            }

            try {
                $process->run(function ($type, $buffer) use ($out) {
                    if ($type === Process::OUT) {
                        fwrite($out, $buffer);
                    }
                });
            } finally {
                fclose($out);
                @unlink($defaultsFile);
            }

            if (! $process->isSuccessful() || ! is_file($sqlPath) || filesize($sqlPath) === 0) {
                if (! config('backup.php_fallback_enabled', true)) {
                    throw new RuntimeException('فشل mysqldump: '.$process->getErrorOutput());
                }
                $this->phpMysqlDump($connection, $sqlPath);
            }

            return;
        }

        if (! config('backup.php_fallback_enabled', true)) {
            throw new RuntimeException('mysqldump غير متاح وتعطيل PHP fallback.');
        }

        $this->phpMysqlDump($connection, $sqlPath);
    }

    private function phpMysqlDump(string $connection, string $sqlPath): void
    {
        $tables = DB::connection($connection)->select('SHOW TABLES');
        $key = 'Tables_in_'.config("database.connections.{$connection}.database");
        $sql = "-- PHP fallback dump\nSET FOREIGN_KEY_CHECKS=0;\n";

        foreach ($tables as $tableRow) {
            $table = $tableRow->$key ?? array_values((array) $tableRow)[0] ?? null;
            if (! $table || $this->isExcludedTable((string) $table)) {
                continue;
            }

            $create = DB::connection($connection)->selectOne("SHOW CREATE TABLE `{$table}`");
            $createSql = $create->{'Create Table'} ?? null;
            if ($createSql) {
                $sql .= "DROP TABLE IF EXISTS `{$table}`;\n{$createSql};\n";
            }

            $rows = DB::connection($connection)->table($table)->get();
            foreach ($rows as $row) {
                $cols = array_keys((array) $row);
                $values = array_map(function ($value) {
                    if ($value === null) {
                        return 'NULL';
                    }

                    return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], (string) $value)."'";
                }, array_values((array) $row));
                $sql .= 'INSERT INTO `'.$table.'` (`'.implode('`,`', $cols).'`) VALUES ('.implode(',', $values).");\n";
            }
        }

        $sql .= "SET FOREIGN_KEY_CHECKS=1;\n";
        file_put_contents($sqlPath, $sql);
    }

    private function restoreSqlite(string $connection, string $sqlPath): void
    {
        $database = config("database.connections.{$connection}.database");
        $content = file_get_contents($sqlPath);
        if ($content === false || $content === '') {
            throw new RuntimeException('ملف SQL فارغ.');
        }

        if (str_starts_with($content, 'SQLite format 3')) {
            if (! is_string($database) || $database === ':memory:' || ! is_dir(dirname($database))) {
                throw new RuntimeException('لا يمكن استرجاع ملف SQLite الثنائي على قاعدة :memory:.');
            }
            DB::connection($connection)->disconnect();
            copy($sqlPath, $database);

            return;
        }

        $pdo = DB::connection($connection)->getPdo();
        $pdo->exec('PRAGMA foreign_keys = OFF');
        try {
            // Execute statement-by-statement for broad SQLite compatibility
            foreach ($this->splitSqlStatements($content) as $statement) {
                if ($this->statementTargetsExcludedTable($statement)) {
                    continue;
                }
                $pdo->exec($statement);
            }
        } finally {
            $pdo->exec('PRAGMA foreign_keys = ON');
        }
    }

    /**
     * @return array<int, string>
     */
    private function splitSqlStatements(string $sql): array
    {
        $statements = [];
        $buffer = '';
        foreach (preg_split("/\r\n|\n|\r/", $sql) as $line) {
            $trim = trim($line);
            if ($trim === '' || str_starts_with($trim, '--')) {
                continue;
            }
            if (preg_match('/^(BEGIN|COMMIT|ROLLBACK|PRAGMA)\b/i', $trim)) {
                continue;
            }
            $buffer .= $line."\n";
            if (str_ends_with(rtrim($line), ';')) {
                $statements[] = trim($buffer);
                $buffer = '';
            }
        }
        if (trim($buffer) !== '') {
            $statements[] = trim($buffer);
        }

        return $statements;
    }

    private function statementTargetsExcludedTable(string $statement): bool
    {
        foreach ($this->excludedTables() as $table) {
            $quoted = preg_quote($table, '/');
            if (preg_match(
                '/\b(?:DROP\s+TABLE(?:\s+IF\s+EXISTS)?|CREATE\s+TABLE(?:\s+IF\s+NOT\s+EXISTS)?|INSERT\s+INTO|UPDATE|DELETE\s+FROM|ALTER\s+TABLE)\s+[`"\[]?'.$quoted.'[`"\]]?/i',
                $statement,
            )) {
                return true;
            }
        }

        return false;
    }

    private function restoreMysql(string $connection, string $sqlPath): void
    {
        $sql = file_get_contents($sqlPath);
        if ($sql === false || $sql === '') {
            throw new RuntimeException('ملف SQL فارغ للاسترجاع.');
        }

        // Prefer statement filtering so operational tables from old archives are skipped.
        DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=0');
        try {
            foreach ($this->splitSqlStatements($sql) as $statement) {
                if ($this->statementTargetsExcludedTable($statement)) {
                    continue;
                }
                if (preg_match('/^SET\s+FOREIGN_KEY_CHECKS\b/i', $statement)) {
                    continue;
                }
                DB::connection($connection)->unprepared($statement);
            }
        } finally {
            DB::connection($connection)->statement('SET FOREIGN_KEY_CHECKS=1');
        }
    }

    private function resolveMysqldumpBinary(): ?string
    {
        $configured = config('backup.mysqldump_path');
        if ($configured && is_file($configured)) {
            return $configured;
        }

        foreach (['mysqldump', 'mariadb-dump'] as $bin) {
            $process = Process::fromShellCommandline(
                PHP_OS_FAMILY === 'Windows' ? 'where '.$bin : 'command -v '.$bin
            );
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim(explode("\n", $process->getOutput())[0] ?? '');
                if ($path !== '') {
                    return $path;
                }
            }
        }

        return null;
    }

    private function resolveMysqlClientBinary(): ?string
    {
        foreach (['mysql', 'mariadb'] as $bin) {
            $process = Process::fromShellCommandline(
                PHP_OS_FAMILY === 'Windows' ? 'where '.$bin : 'command -v '.$bin
            );
            $process->run();
            if ($process->isSuccessful()) {
                $path = trim(explode("\n", $process->getOutput())[0] ?? '');
                if ($path !== '') {
                    return $path;
                }
            }
        }

        return null;
    }

    private function writeDefaultsFile(string $path, string $host, string $port, string $user, string $password): void
    {
        $content = "[client]\nhost={$host}\nport={$port}\nuser={$user}\npassword=\"{$password}\"\n";
        file_put_contents($path, $content);
        @chmod($path, 0600);
    }
}
