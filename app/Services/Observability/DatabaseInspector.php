<?php

namespace App\Services\Observability;

use Illuminate\Database\Connection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Read-only introspection of the application database.
 *
 * Built on Laravel's own cross-driver schema API (Schema::getTables/getColumns/
 * getIndexes/getForeignKeys) rather than hand-written per-driver SQL. That matters
 * more than it looks: SQLiteBuilder::getTables() already catches a missing `dbstat`
 * extension and falls back to a legacy query for SQLite < 3.37 — both of which apply
 * to this machine (3.33.0, no dbstat). Re-implementing that by hand would miss them.
 *
 * Nothing here writes. There is deliberately no VACUUM/OPTIMIZE surface.
 */
class DatabaseInspector
{
    /**
     * Connection settings safe to render.
     *
     * `password` and `url` are absent by construction — not masked, not truncated.
     * A DSN in `url` can carry full credentials, so it never leaves this class.
     *
     * @var array<int, string>
     */
    protected const SAFE_CONFIG_KEYS = [
        'driver', 'host', 'port', 'database', 'username', 'unix_socket',
        'charset', 'collation', 'prefix', 'engine', 'schema', 'search_path',
        'strict', 'foreign_key_constraints', 'journal_mode', 'busy_timeout',
        'transaction_mode', 'sslmode',
    ];

    public function connection(?string $name = null): array
    {
        $connection = DB::connection($name);
        $config = config('database.connections.'.$connection->getName(), []);

        $safe = [];

        foreach (self::SAFE_CONFIG_KEYS as $key) {
            if (! array_key_exists($key, $config)) {
                continue;
            }

            $value = $config[$key];

            if ($value === null || $value === '' || is_array($value)) {
                continue;
            }

            $safe[$key] = is_bool($value) ? ($value ? 'true' : 'false') : (string) $value;
        }

        // On SQLite the "database" is a filesystem path; show it as a name plus its
        // real on-disk size, which is the only true total size available.
        $database = $connection->getDatabaseName();

        return [
            'name' => $connection->getName(),
            'driver' => $connection->getDriverName(),
            'driver_title' => $connection->getDriverTitle(),
            'version' => $this->safely(fn () => $connection->getServerVersion(), 'غير معروف'),
            'database' => $this->isSqlite($connection) ? basename($database) : $database,
            'path' => $this->isSqlite($connection) ? $database : null,
            'settings' => $safe,
            'available_connections' => array_keys(config('database.connections', [])),
        ];
    }

    /**
     * Every table with its row count, size and index count.
     *
     * @return array<int, array<string, mixed>>
     */
    public function tables(?string $name = null): array
    {
        $connection = DB::connection($name);
        $schema = Schema::connection($connection->getName());

        $tables = $this->safely(fn () => $schema->getTables(), []);
        $rowCounts = $this->rowCounts($connection, array_column($tables, 'name'));
        $exactRows = ! $this->isMySql($connection);

        return collect($tables)
            ->map(function (array $table) use ($connection, $schema, $rowCounts, $exactRows): array {
                $name = $table['name'];
                $rows = $rowCounts[$name] ?? 0;

                // MySQL/Postgres report real bytes. SQLite only does when dbstat is
                // compiled in — otherwise fall back to a clearly-labelled estimate.
                $realSize = $table['size'] ?? null;
                $estimated = $realSize === null && $rows > 0
                    ? $this->estimatePayloadBytes($connection, $schema, $name)
                    : null;

                return [
                    'name' => $name,
                    'rows' => $rows,
                    'rows_exact' => $exactRows,
                    'size_bytes' => $realSize ?? $estimated,
                    'size_is_estimate' => $realSize === null,
                    'size_human' => $this->humanBytes($realSize ?? $estimated),
                    'indexes' => count($this->safely(fn () => $schema->getIndexes($name), [])),
                    'engine' => $table['engine'] ?? null,
                    'collation' => $table['collation'] ?? null,
                    'comment' => $table['comment'] ?? null,
                ];
            })
            ->sortByDesc('rows')
            ->values()
            ->all();
    }

    /**
     * @param  array<int, array<string, mixed>>|null  $tables  Pass through to avoid re-querying.
     * @return array<string, mixed>
     */
    public function totals(?string $name = null, ?array $tables = null): array
    {
        $connection = DB::connection($name);
        $tables ??= $this->tables($name);

        $fileSize = $this->totalSizeBytes($connection);
        $summedSize = collect($tables)->sum('size_bytes');

        return [
            'tables' => count($tables),
            'rows' => collect($tables)->sum('rows'),
            'indexes' => collect($tables)->sum('indexes'),
            // The authoritative total where one exists (SQLite page count, MySQL sum
            // of data+index length); falls back to the sum of per-table figures.
            'size_bytes' => $fileSize ?? $summedSize,
            'size_human' => $this->humanBytes($fileSize ?? $summedSize),
            'size_is_estimate' => $fileSize === null,
            'free_bytes' => $this->freeBytes($connection),
            'free_human' => $this->humanBytes($this->freeBytes($connection)),
        ];
    }

    /**
     * Findings worth acting on — the real value of this screen over raw numbers.
     *
     * @return array<int, array{level: string, title: string, detail: string}>
     */
    public function driverNotes(?string $name = null, ?array $tables = null): array
    {
        $connection = DB::connection($name);
        $notes = [];

        if ($this->isSqlite($connection)) {
            $journal = $this->safely(fn () => (string) $connection->scalar('PRAGMA journal_mode'), '');

            if ($journal !== '' && strtolower($journal) !== 'wal') {
                $notes[] = [
                    'level' => 'warning',
                    'title' => 'وضع السجل: '.$journal,
                    'detail' => 'وضع WAL يسمح بالقراءة أثناء الكتابة ويقلّل التعارض في تطبيقات الويب. '
                        .'يمكن تفعيله بضبط journal_mode في إعداد اتصال sqlite.',
                ];
            }

            if (! $this->hasDbstat($connection)) {
                $notes[] = [
                    'level' => 'info',
                    'title' => 'أحجام الجداول تقديرية',
                    'detail' => 'امتداد dbstat غير مُجمَّع في هذه النسخة من SQLite، فلا يمكن قراءة '
                        .'الحجم الحقيقي لكل جدول. المعروض تقدير لحجم البيانات من أطوال الأعمدة، '
                        .'ولا يشمل الفهارس ولا مساحة الصفحات. الحجم الكلي للملف دقيق.',
                ];
            }

            $foreignKeys = $this->safely(fn () => (int) $connection->scalar('PRAGMA foreign_keys'), 1);

            if ($foreignKeys !== 1) {
                $notes[] = [
                    'level' => 'danger',
                    'title' => 'قيود المفاتيح الأجنبية معطّلة',
                    'detail' => 'قد تُحفظ بيانات يتيمة دون اعتراض. فعّل DB_FOREIGN_KEYS=true.',
                ];
            }

            $free = $this->freeBytes($connection);
            $total = $this->totalSizeBytes($connection);

            if ($free !== null && $total !== null && $total > 0 && ($free / $total) > 0.25) {
                $notes[] = [
                    'level' => 'warning',
                    'title' => 'مساحة حرة كبيرة داخل الملف',
                    'detail' => $this->humanBytes($free).' من '.$this->humanBytes($total)
                        .' صفحات حرة. تشغيل VACUUM يدوياً يستعيدها.',
                ];
            }
        }

        $tables ??= $this->tables($name);

        $unindexed = collect($tables)
            ->filter(fn (array $t): bool => $t['rows'] > 500 && $t['indexes'] <= 1)
            ->pluck('name');

        if ($unindexed->isNotEmpty()) {
            $notes[] = [
                'level' => 'warning',
                'title' => 'جداول كبيرة بلا فهارس كافية',
                'detail' => $unindexed->implode('، ').' — الاستعلامات عليها ستمسح الجدول كاملاً.',
            ];
        }

        if ($notes === []) {
            $notes[] = [
                'level' => 'success',
                'title' => 'لا توجد ملاحظات',
                'detail' => 'إعدادات قاعدة البيانات تبدو سليمة.',
            ];
        }

        return $notes;
    }

    /**
     * Columns, indexes and foreign keys for one table.
     *
     * @return array<string, mixed>|null Null when the table does not exist.
     */
    public function table(string $table, ?string $name = null): ?array
    {
        $connection = DB::connection($name);
        $schema = Schema::connection($connection->getName());

        // Validated against the real table list — a user-supplied name never reaches
        // a query unchecked.
        if (! in_array($table, $this->tableNames($name), true)) {
            return null;
        }

        return [
            'name' => $table,
            'rows' => $this->safely(fn () => $connection->table($table)->count(), 0),
            'columns' => array_map(fn (array $column): array => [
                'name' => $column['name'],
                'type' => $column['type'],
                'nullable' => (bool) $column['nullable'],
                'default' => $column['default'] === null ? null : (string) $column['default'],
                'auto_increment' => (bool) ($column['auto_increment'] ?? false),
                'comment' => $column['comment'] ?? null,
            ], $this->safely(fn () => $schema->getColumns($table), [])),
            'indexes' => array_map(fn (array $index): array => [
                'name' => $index['name'],
                'columns' => $index['columns'],
                'unique' => (bool) $index['unique'],
                'primary' => (bool) $index['primary'],
            ], $this->safely(fn () => $schema->getIndexes($table), [])),
            'foreign_keys' => array_map(fn (array $fk): array => [
                'name' => $fk['name'],
                'columns' => $fk['columns'],
                'foreign_table' => $fk['foreign_table'],
                'foreign_columns' => $fk['foreign_columns'],
                'on_delete' => $fk['on_delete'] ?? null,
                'on_update' => $fk['on_update'] ?? null,
            ], $this->safely(fn () => $schema->getForeignKeys($table), [])),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function tableNames(?string $name = null): array
    {
        return $this->safely(
            fn () => Schema::connection(DB::connection($name)->getName())->getTableListing(schemaQualified: false),
            []
        );
    }

    // ------------------------------------------------------------------ internals

    /**
     * @param  array<int, string>  $tables
     * @return array<string, int>
     */
    protected function rowCounts(Connection $connection, array $tables): array
    {
        // On MySQL a COUNT(*) per table is a full scan on InnoDB. information_schema
        // gives an approximate count for every table in one cheap query instead.
        if ($this->isMySql($connection)) {
            $approx = $this->safely(fn () => collect($connection->select(
                'select table_name as name, table_rows as rows from information_schema.tables where table_schema = ?',
                [$connection->getDatabaseName()]
            ))->pluck('rows', 'name')->all(), []);

            if ($approx !== []) {
                return array_map(fn ($rows): int => (int) $rows, $approx);
            }
        }

        $counts = [];

        foreach ($tables as $table) {
            $counts[$table] = $this->safely(fn () => $connection->table($table)->count(), 0);
        }

        return $counts;
    }

    /**
     * Approximate stored payload, by summing the byte length of every column value.
     *
     * This is NOT the table's storage footprint — it excludes indexes, page overhead
     * and free space. Always surfaced to the UI as an estimate.
     */
    protected function estimatePayloadBytes(Connection $connection, $schema, string $table): ?int
    {
        return $this->safely(function () use ($connection, $schema, $table): ?int {
            $columns = $schema->getColumns($table);

            if ($columns === []) {
                return null;
            }

            $grammar = $connection->getQueryGrammar();

            $expression = collect($columns)
                ->map(fn (array $column): string => sprintf(
                    'COALESCE(LENGTH(CAST(%s AS BLOB)), 0)',
                    $grammar->wrap($column['name'])
                ))
                ->implode(' + ');

            return (int) $connection->scalar(
                'select COALESCE(SUM('.$expression.'), 0) from '.$grammar->wrapTable($table)
            );
        }, null);
    }

    protected function totalSizeBytes(Connection $connection): ?int
    {
        if ($this->isSqlite($connection)) {
            return $this->safely(function () use ($connection): ?int {
                $pageCount = (int) $connection->scalar('PRAGMA page_count');
                $pageSize = (int) $connection->scalar('PRAGMA page_size');

                return $pageCount * $pageSize;
            }, null);
        }

        if ($this->isMySql($connection)) {
            return $this->safely(fn (): ?int => (int) $connection->scalar(
                'select sum(data_length + index_length) from information_schema.tables where table_schema = ?',
                [$connection->getDatabaseName()]
            ), null);
        }

        return null;
    }

    protected function freeBytes(Connection $connection): ?int
    {
        if ($this->isSqlite($connection)) {
            return $this->safely(function () use ($connection): ?int {
                $free = (int) $connection->scalar('PRAGMA freelist_count');
                $pageSize = (int) $connection->scalar('PRAGMA page_size');

                return $free * $pageSize;
            }, null);
        }

        if ($this->isMySql($connection)) {
            return $this->safely(fn (): ?int => (int) $connection->scalar(
                'select sum(data_free) from information_schema.tables where table_schema = ?',
                [$connection->getDatabaseName()]
            ), null);
        }

        return null;
    }

    protected function hasDbstat(Connection $connection): bool
    {
        return $this->safely(function () use ($connection): bool {
            $connection->scalar('select 1 from dbstat limit 1');

            return true;
        }, false);
    }

    protected function isSqlite(Connection $connection): bool
    {
        return $connection->getDriverName() === 'sqlite';
    }

    protected function isMySql(Connection $connection): bool
    {
        return in_array($connection->getDriverName(), ['mysql', 'mariadb'], true);
    }

    public function humanBytes(?int $bytes): ?string
    {
        if ($bytes === null) {
            return null;
        }

        if ($bytes < 1024) {
            return $bytes.' B';
        }

        $units = ['KB', 'MB', 'GB', 'TB'];
        $value = $bytes / 1024;
        $unit = 0;

        while ($value >= 1024 && $unit < count($units) - 1) {
            $value /= 1024;
            $unit++;
        }

        $formatted = number_format($value, $value >= 100 ? 0 : 1);

        // Strip a trailing ".0" only — a blanket rtrim('0') turns "400" into "4".
        if (str_contains($formatted, '.')) {
            $formatted = rtrim(rtrim($formatted, '0'), '.');
        }

        return $formatted.' '.$units[$unit];
    }

    protected function safely(\Closure $callback, mixed $fallback): mixed
    {
        try {
            return $callback();
        } catch (\Throwable) {
            return $fallback;
        }
    }
}
