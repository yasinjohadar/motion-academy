<?php

namespace Tests\Feature\Observability;

use App\Models\User;
use App\Services\Observability\DatabaseInspector;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DatabaseInspectorTest extends TestCase
{
    use RefreshDatabase;

    protected function inspector(): DatabaseInspector
    {
        return app(DatabaseInspector::class);
    }

    // ---------------------------------------------------------------- redaction

    public function test_the_database_password_never_appears_in_the_connection_payload(): void
    {
        $secret = 'SuperSecretDbPassword!2026';

        config()->set('database.connections.sqlite.password', $secret);
        config()->set('database.connections.sqlite.url', 'mysql://root:'.$secret.'@db.internal:3306/app');

        $payload = json_encode($this->inspector()->connection(), JSON_UNESCAPED_SLASHES);

        $this->assertStringNotContainsString($secret, $payload);
        $this->assertStringNotContainsString('url', $payload);
    }

    public function test_the_connection_payload_has_no_password_key_at_all(): void
    {
        config()->set('database.connections.sqlite.password', 'anything');

        // Absent by construction, not masked — a masked key still signals its existence
        // and invites someone to widen the whitelist later.
        $this->assertArrayNotHasKey('password', $this->inspector()->connection()['settings']);
    }

    public function test_the_whitelist_never_admits_a_credential_key(): void
    {
        $forbidden = ['password', 'url', 'secret', 'token', 'key'];

        $reflection = new \ReflectionClassConstant(DatabaseInspector::class, 'SAFE_CONFIG_KEYS');

        foreach ($reflection->getValue() as $key) {
            $this->assertNotContains($key, $forbidden, "Config key [{$key}] must not be exposed.");
        }
    }

    // ---------------------------------------------------------------- connection

    public function test_connection_reports_the_real_driver_and_version(): void
    {
        $connection = $this->inspector()->connection();

        $this->assertSame('sqlite', $connection['driver']);
        $this->assertSame('SQLite', $connection['driver_title']);
        $this->assertNotSame('غير معروف', $connection['version']);
        $this->assertContains('mysql', $connection['available_connections']);
    }

    // ---------------------------------------------------------------- tables

    public function test_tables_include_real_application_tables_with_row_counts(): void
    {
        User::factory()->count(3)->create();

        $tables = collect($this->inspector()->tables());

        $users = $tables->firstWhere('name', 'users');

        $this->assertNotNull($users, 'The users table was not reported.');
        $this->assertSame(3, $users['rows']);
        $this->assertTrue($users['rows_exact'], 'SQLite counts are exact, not approximated.');
    }

    public function test_tables_are_ordered_by_row_count(): void
    {
        User::factory()->count(5)->create();

        $rows = collect($this->inspector()->tables())->pluck('rows')->all();

        $this->assertSame($rows, collect($rows)->sortDesc()->values()->all());
    }

    public function test_table_size_is_flagged_as_an_estimate_when_dbstat_is_unavailable(): void
    {
        User::factory()->count(3)->create();

        $users = collect($this->inspector()->tables())->firstWhere('name', 'users');

        // This SQLite build has no dbstat, so a real per-table size cannot exist.
        $this->assertTrue($users['size_is_estimate']);
        $this->assertGreaterThan(0, $users['size_bytes'], 'The payload estimate should not be zero for populated tables.');
    }

    public function test_empty_tables_report_no_size_rather_than_a_fabricated_one(): void
    {
        $empty = collect($this->inspector()->tables())->firstWhere('rows', 0);

        $this->assertNotNull($empty);
        $this->assertNull($empty['size_bytes']);
    }

    public function test_index_counts_are_reported(): void
    {
        $notifications = collect($this->inspector()->tables())->firstWhere('name', 'notifications');

        $this->assertGreaterThan(0, $notifications['indexes']);
    }

    // ---------------------------------------------------------------- totals

    public function test_totals_match_the_table_list(): void
    {
        User::factory()->count(4)->create();

        $tables = $this->inspector()->tables();
        $totals = $this->inspector()->totals(tables: $tables);

        $this->assertSame(count($tables), $totals['tables']);
        $this->assertSame(collect($tables)->sum('rows'), $totals['rows']);
    }

    public function test_the_total_size_comes_from_the_real_page_count_on_sqlite(): void
    {
        $totals = $this->inspector()->totals();

        $pageCount = (int) \DB::scalar('PRAGMA page_count');
        $pageSize = (int) \DB::scalar('PRAGMA page_size');

        $this->assertSame($pageCount * $pageSize, $totals['size_bytes']);
        $this->assertFalse($totals['size_is_estimate'], 'The file total is exact even when per-table sizes are not.');
    }

    // ---------------------------------------------------------------- notes

    public function test_notes_flag_a_non_wal_journal_mode(): void
    {
        $titles = collect($this->inspector()->driverNotes())->pluck('title')->implode(' | ');

        $this->assertStringContainsString('وضع السجل', $titles);
    }

    public function test_notes_explain_why_sizes_are_estimated(): void
    {
        $titles = collect($this->inspector()->driverNotes())->pluck('title')->implode(' | ');

        $this->assertStringContainsString('تقديرية', $titles);
    }

    // ---------------------------------------------------------------- table detail

    public function test_table_detail_returns_columns_indexes_and_foreign_keys(): void
    {
        $detail = $this->inspector()->table('notification_preferences');

        $this->assertNotNull($detail);
        $this->assertContains('category', collect($detail['columns'])->pluck('name')->all());
        $this->assertNotEmpty($detail['indexes']);
        $this->assertContains('users', collect($detail['foreign_keys'])->pluck('foreign_table')->all());
    }

    public function test_an_unknown_table_returns_null_instead_of_querying(): void
    {
        $this->assertNull($this->inspector()->table('definitely_not_a_table'));
    }

    public function test_a_sql_injection_attempt_in_the_table_name_is_rejected(): void
    {
        $this->assertNull($this->inspector()->table('users; drop table users'));
        $this->assertNull($this->inspector()->table('users--'));

        // The real table must still be intact.
        $this->assertNotNull($this->inspector()->table('users'));
    }

    // ---------------------------------------------------------------- formatting

    public function test_bytes_are_formatted_for_humans(): void
    {
        $inspector = $this->inspector();

        $this->assertSame('512 B', $inspector->humanBytes(512));
        $this->assertSame('1 KB', $inspector->humanBytes(1024));
        $this->assertSame('1.5 KB', $inspector->humanBytes(1536));
        $this->assertSame('400 KB', $inspector->humanBytes(409600));
        $this->assertSame('1 MB', $inspector->humanBytes(1048576));
        $this->assertNull($inspector->humanBytes(null));
    }
}
