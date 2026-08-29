<?php

namespace Tests\Feature;

use Illuminate\Database\Schema\Builder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class MigrationCompatibilityTest extends TestCase
{
    use RefreshDatabase;

    /**
     * SQLite (used for local dev and this whole test suite) has no limit on
     * identifier length, so a too-long index name only ever surfaces once a
     * real MySQL/MariaDB database runs the same migrations — exactly what
     * happened with product_inventory_movements' auto-generated index name
     * (65 characters, one past MySQL's hard 64-character limit), caught
     * only by testing against a real MySQL server during installer
     * verification. This test can't use a real MySQL connection either
     * (none available in CI), but Laravel computes the same index name
     * regardless of driver, so asserting the length of whatever name SQLite
     * actually recorded still catches this class of bug before it reaches
     * a real MySQL deploy.
     */
    public function test_no_generated_index_name_exceeds_mysqls_64_character_limit(): void
    {
        $names = DB::select("SELECT name FROM sqlite_master WHERE type IN ('index', 'table')");

        $tooLong = collect($names)
            ->pluck('name')
            ->filter(fn ($name) => ! str_starts_with($name, 'sqlite_'))
            ->filter(fn ($name) => strlen($name) > 64)
            ->values();

        $this->assertTrue(
            $tooLong->isEmpty(),
            "These identifiers exceed MySQL's 64-character limit: {$tooLong->join(', ')}"
        );
    }

    /**
     * A plain `$table->string('email')->unique()` under utf8mb4 is a
     * 255 * 4 = 1020-byte index — over both the classic 767-byte InnoDB
     * single-column prefix limit (older row formats) and a target host's
     * own reported 1000-byte cap, genuinely reproduced with a real
     * restricted-row-format MariaDB server. AppServiceProvider::boot()
     * lowers this to 191 chars (764 bytes) for every unlengthed string
     * column app-wide — including in Laravel's own stock migrations
     * (users.email, sessions.id, password_reset_tokens.email, ...), which
     * is why this is set globally rather than patched migration-by-migration.
     */
    public function test_default_string_length_is_lowered_for_older_mysql_index_limits(): void
    {
        $this->assertSame(191, Builder::$defaultStringLength);
    }
}
