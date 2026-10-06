# Phase 1R — Postgres Portability and the Parity Gate

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.
>
> **Prerequisite:** [Phase 0R](2026-10-05-phase-0r-close-remaining-ground-truth.md) complete.

**Goal:** Run all 166 migrations fresh against PostgreSQL, fix every driver-specific failure, and replace the placeholder schema test with a real parity gate that compares a fresh Postgres schema against SQLite column by column.

**Architecture:** No behaviour changes; portability only. The parity test is the permanent gate the spec calls "the sleeper risk" (§14) — migrating without erroring is not evidence the schemas match.

**Tech Stack:** Laravel 12, PHP 8.2+, PostgreSQL 14+, SQLite 3, MySQL.

**Spec:** §11 (database and migration strategy), §13 (testing strategy), §14 (risks).

**Closes:** G13, G17, G18, G19.

---

## Global Constraints

- **Never rewrite an applied migration's effect.** Make driver-specific paths conditional only, following the established pattern in `database/migrations/2026_04_10_160000_harden_inventory_ledger_tables.php`.
- All schema changes ship as new migrations. Editing an existing migration to change what it produces is forbidden.
- One commit per migration fix, so a reviewer can isolate which migration broke.
- Every new migration must work on all three engines. Test all three before committing.
- Commit after every task.

---

## The gap this closes

`tests/Feature/SchemaParityTest.php` is:

```php
public function test_all_migrations_run_successfully_on_sqlite(): void
{
    $this->assertTrue(true);
}
```

It compares nothing, and its name states something it does not do. Spec §11.3
requires "a schema parity test comparing a fresh Postgres schema against MySQL —
columns, types, nullability, indexes, foreign keys — as a permanent gate."

No migration has ever been run against Postgres. The local `.env` points at
`127.0.0.1:3306`, and Postgres is unavailable, so this phase starts by fixing
the environment rather than the code.

---

## File Structure

**Created:**
- `docker-compose.pgsql.yml` — a local Postgres for parity testing.
- `tests/Support/SchemaIntrospector.php` — reads a schema into a comparable array.
- `tests/Feature/SchemaParityTest.php` — rewritten as a real gate.

**Modified:**
- `database/migrations/2026_04_10_160000_harden_inventory_ledger_tables.php` — replace MySQL-only index inspection with driver-aware logic.
- Any migration that fails on Postgres, one commit each.
- `.env.testing.pgsql` — a Postgres test configuration.

**Responsibility note:** this phase produces a schema that is provably equivalent across three engines, and a test that fails when it stops being equivalent.

---

## Task 1: Get a real Postgres running

Nothing else in this phase is verifiable without it. The current configuration
points at port 3306, which is MySQL.

**Files:**
- Create: `docker-compose.pgsql.yml`

**Interfaces:**
- Consumes: Docker.
- Produces: a Postgres instance the parity test can connect to.

- [ ] **Step 1: Write the compose file**

```yaml
services:
  pgsql:
    image: postgres:16-alpine
    container_name: hotelms-parity-pgsql
    environment:
      POSTGRES_DB: hotelms_parity
      POSTGRES_USER: hotelms
      POSTGRES_PASSWORD: hotelms_parity_pw
    ports:
      - "5433:5432"
    healthcheck:
      test: ["CMD-SHELL", "pg_isready -U hotelms -d hotelms_parity"]
      interval: 5s
      timeout: 5s
      retries: 12
    tmpfs:
      - /var/lib/postgresql/data
```

`tmpfs` keeps the database disposable — parity testing migrates fresh constantly
and does not need durability.

- [ ] **Step 2: Start it and confirm**

```powershell
docker compose -f docker-compose.pgsql.yml up -d
docker compose -f docker-compose.pgsql.yml ps
```

Expected: healthy. If port 5433 is taken, change the host-side port and update
the connection string in Step 3 to match.

- [ ] **Step 3: Write the test environment file**

```dotenv
DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5433
DB_DATABASE=hotelms_parity
DB_USERNAME=hotelms
DB_PASSWORD=hotelms_parity_pw
```

- [ ] **Step 4: Prove the connection works**

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_HOST='127.0.0.1'; $env:DB_PORT='5433'
$env:DB_DATABASE='hotelms_parity'; $env:DB_USERNAME='hotelms'; $env:DB_PASSWORD='hotelms_parity_pw'
php artisan db:show
```

Expected: the Postgres connection detail table. If this fails with an SSL
negotiation error, the earlier `127.0.0.1:3306` symptom is a driver/port
mismatch, not an SSL setting — confirm the port first.

- [ ] **Step 5: Run migrations and capture the first failure**

```powershell
php artisan migrate:fresh --force --database=pgsql
```

Expect failure. Record the exact migration and message; Task 2 starts there.
Commit: `chore: add local postgres for schema parity testing`

---

## Task 2: Fix driver-specific migration failures

Work one migration per commit, in the order Postgres reports them. Each fix
must leave all three engines working.

**Files:**
- Modify: whatever migration fails, one at a time.

**Interfaces:**
- Consumes: Task 1.
- Produces: `migrate:fresh` completing on Postgres.

- [ ] **Step 1: Audit for known MySQL-only constructs before iterating**

Faster than discovering them one at a time.

```powershell
rg -n "SHOW INDEX|SHOW TABLE|ENGINE=|AUTO_INCREMENT|UNSIGNED|ZEROFILL|datetime\(|timestamp\(|->enum\(|DB::statement|information_schema|->change\(\)" database/migrations/
```

Expected suspects, in rough order of likelihood:

| Construct | Problem on Postgres | Fix |
|---|---|---|
| `SHOW INDEX` | Syntax error | Guard on `DB::getDriverName() === 'mysql'`; use `pg_indexes` or skip |
| `ENGINE=InnoDB` | Unknown table option | Guard on driver |
| `->enum()` | Native enum, but `->change()` behaves differently | Use `string` + a check constraint, or guard the change |
| `datetime(0)` / `timestamp(0)` with precision | Different precision semantics | Drop the explicit precision; let the default apply |
| `unsignedBigInteger` | No unsigned integers | Accepted by Laravel as `bigint`; verify no logic depends on unsignedness |
| `dropColumn` with multiple args | Requires `doctrine/dbal` on some versions | Verify the Laravel version's native support first |

- [ ] **Step 2: Fix `2026_04_10_160000_harden_inventory_ledger_tables.php` first**

This is the file the spec names, and it queries index metadata.

```powershell
Get-Content database/migrations/2026_04_10_160000_harden_inventory_ledger_tables.php
```

Replace the `SHOW INDEX` inspection with driver-aware logic. The pattern to
follow:

```php
$driver = DB::getDriverName();

if ($driver === 'mysql') {
    // existing SHOW INDEX logic, unchanged
} elseif ($driver === 'pgsql') {
    $exists = DB::selectOne(
        'select 1 from pg_indexes where tablename = ? and indexname = ?',
        [$table, $indexName]
    );
} else {
    // sqlite: use PRAGMA index_list
}
```

Never change the MySQL branch. It is production.

- [ ] **Step 3: Verify all three engines after each fix**

```powershell
# sqlite
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE="$env:TEMP\p.sqlite"
php artisan migrate:fresh --force

# pgsql
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'
php artisan migrate:fresh --force --database=pgsql

# suite
php artisan test
```

A fix that breaks SQLite is not a fix. Commit per migration:
`fix: make <migration-name> portable across mysql pgsql and sqlite`

- [ ] **Step 4: Loop until Postgres completes**

```powershell
php artisan migrate:fresh --force --database=pgsql
```

Expected: `166 DONE, 0 FAIL`.

- [ ] **Step 5: Audit for driver-specific runtime queries**

Migrations are only half of it. Application queries that only ever ran on MySQL
will fail on Postgres.

```powershell
rg -n "SHOW |information_schema|IFNULL\(|DATE_FORMAT|STR_TO_DATE|GROUP_CONCAT|FIND_IN_SET|ON DUPLICATE KEY|INSERT IGNORE|RAND\(\)|NOW\(\)" app/
```

Fix each with a driver-aware branch or a Laravel-native equivalent. Prefer the
native equivalent — `whereRaw('COALESCE(...)')` over an `IFNULL` branch.

- [ ] **Step 6: Verify the suite on Postgres too**

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'
$env:DB_DATABASE='hotelms_parity'
php artisan test
```

Expect some failures — the suite has only ever run on SQLite. Fix them the same
way: one commit each.

Commit: `fix: replace mysql-only queries with portable equivalents`

---

## Task 3: Replace the parity gate

The spec requires columns, types, nullability, indexes and foreign keys. Migrating
without erroring is not evidence any of those match.

**Files:**
- Create: `tests/Support/SchemaIntrospector.php`
- Rewrite: `tests/Feature/SchemaParityTest.php`

**Interfaces:**
- Consumes: Task 2.
- Produces: `SchemaIntrospector::snapshot(string $connection): array` and a parity test that fails on any divergence.

- [ ] **Step 1: Write the introspector**

```php
<?php

namespace Tests\Support;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Reads a database schema into a comparable, driver-neutral array.
 *
 * Type names are normalised because the same logical type is spelled
 * differently per engine: `bigint` vs `bigint`, `varchar(255)` vs
 * `character varying(255)`, `datetime` vs `timestamp without time zone`.
 */
class SchemaIntrospector
{
    /** @return array<string, array<string, array<string, mixed>>> */
    public function snapshot(string $connection): array
    {
        $tables = $this->tables($connection);
        $out = [];

        foreach ($tables as $table) {
            $out[$table] = [
                'columns'     => $this->columns($connection, $table),
                'indexes'     => $this->indexes($connection, $table),
                'foreignKeys' => $this->foreignKeys($connection, $table),
            ];
        }

        ksort($out);

        return $out;
    }

    /** @return list<string> */
    private function tables(string $connection): array
    {
        $driver = DB::connection($connection)->getDriverName();

        $rows = match ($driver) {
            'pgsql' => DB::connection($connection)->select(
                "select tablename from pg_tables where schemaname = 'public' order by tablename"
            ),
            'mysql' => DB::connection($connection)->select(
                'show tables'
            ),
            default => DB::connection($connection)->select(
                "select name from sqlite_master where type = 'table' and name not like 'sqlite_%' order by name"
            ),
        };

        $names = array_map(fn ($r) => (string) (array_values((array) $r)[0]), $rows);

        sort($names);

        return array_values(array_filter($names, fn ($n) => ! str_starts_with($n, 'migrations')));
    }
}
```

Continue with `columns()`, `indexes()` and `foreignKeys()`, each branching on
driver and normalising type names through a shared map:

```php
private function normaliseType(string $type): string
{
    $type = strtolower(trim($type));

    return match (true) {
        str_contains($type, 'character varying') => 'varchar',
        str_contains($type, 'timestamp without time zone') => 'datetime',
        str_contains($type, 'timestamp with time zone') => 'timestamp',
        str_contains($type, 'bool') => 'tinyint(1)',
        default => $type,
    };
}
```

- [ ] **Step 2: Write the parity test**

The test must compare against a real second engine, so it skips when Postgres is
unavailable — loudly, not silently.

```php
<?php

namespace Tests\Feature;

use Tests\Support\SchemaIntrospector;
use Tests\TestCase;

class SchemaParityTest extends TestCase
{
    public function test_sqlite_and_pgsql_schemas_match(): void
    {
        if (! $this->pgsqlAvailable()) {
            $this->markTestSkipped(
                'PostgreSQL is not reachable. Start it with '
                . 'docker compose -f docker-compose.pgsql.yml up -d to run the parity gate.'
            );
        }

        $introspector = new SchemaIntrospector();

        $sqlite = $introspector->snapshot('sqlite');
        $pgsql  = $introspector->snapshot('pgsql');

        $onlySqlite = array_diff(array_keys($sqlite), array_keys($pgsql));
        $onlyPgsql  = array_diff(array_keys($pgsql), array_keys($sqlite));

        $this->assertSame([], array_values($onlySqlite), 'Tables missing from PostgreSQL.');
        $this->assertSame([], array_values($onlyPgsql), 'Tables missing from SQLite.');

        foreach (array_intersect(array_keys($sqlite), array_keys($pgsql)) as $table) {
            $this->assertSame(
                $sqlite[$table]['columns'],
                $pgsql[$table]['columns'],
                "Column mismatch on [{$table}].",
            );

            $this->assertSame(
                $this->normaliseIndexes($sqlite[$table]['indexes']),
                $this->normaliseIndexes($pgsql[$table]['indexes']),
                "Index mismatch on [{$table}].",
            );

            $this->assertSame(
                $sqlite[$table]['foreignKeys'],
                $pgsql[$table]['foreignKeys'],
                "Foreign key mismatch on [{$table}].",
            );
        }
    }

    private function pgsqlAvailable(): bool
    {
        try {
            DB::connection('pgsql')->select('select 1');

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
```

The skip is deliberate but loud — it names the exact command. A parity gate that
skips silently is how §14's sleeper risk lands.

- [ ] **Step 3: Make Postgres available to the test suite**

Add a `pgsql` connection to `config/database.php` and a `.env.testing.pgsql`.
Do not change the default `sqlite` connection: the committed default engine is
SQLite per Phase 0.

- [ ] **Step 4: Run the gate and fix what it finds**

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'; $env:DB_DATABASE='hotelms_parity'
php artisan test --filter=SchemaParityTest
```

Expect failures. Each one is a real divergence. Fix with a new migration —
never by editing an applied one. One commit per divergence:
`fix: align <table> schema across mysql pgsql and sqlite`

- [ ] **Step 5: Verify the gate catches a deliberate break**

A parity test that has never failed is untested. Introduce a temporary
divergence, confirm the test fails, then revert.

```php
// temporarily add to a test-only migration
$table->addColumn('parity_probe', 'string')->nullable();
```

Expected: the parity test fails naming the column. Then remove it and confirm
the test passes again.

- [ ] **Step 6: Add the gate to CI**

A permanent gate that only runs locally is not permanent. Add a Postgres service
to the GitHub Actions workflow and run `php artisan test --filter=SchemaParityTest`
there.

Commit: `test: replace placeholder schema test with a real cross-engine parity gate`

---

## Task 4: Make `sync_deleted_at` portable

All 15 `add_sync_fields` migrations use `$table->softDeletesTz('sync_deleted_at')`,
which renders differently per engine — `datetime` on MySQL, `timestamp` on
Postgres, `datetime` on SQLite. The parity test in Task 3 will flag this.

Also note `softDeletesTz` is semantically wrong here. It registers Eloquent soft
delete behaviour for a column that is a sync tombstone, not a model delete flag.

**Files:**
- Create: `database/migrations/2026_10_05_100010_normalize_sync_deleted_at_type.php`

**Interfaces:**
- Consumes: Task 3.
- Produces: one consistent `sync_deleted_at` type across engines.

- [ ] **Step 1: Confirm the divergence**

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'
php artisan test --filter=SchemaParityTest
```

- [ ] **Step 2: Write a migration that normalises the type**

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Normalise sync_deleted_at to a plain nullable timestamp.
 *
 * The 15 add_sync_fields migrations used softDeletesTz(), which renders as
 * datetime on MySQL, timestamp on Postgres and datetime on SQLite. It also
 * registers Eloquent soft-delete behaviour, which is wrong for a sync
 * tombstone: the row is not deleted, it is superseded.
 *
 * Tables: the 15 that received sync columns in 2026_10_04_1000{01..15}.
 */
return new class extends Migration
{
    /** @var list<string> */
    private const TABLES = [
        'pos_dockets', 'pos_docket_items', 'pos_docket_payments', 'pos_print_logs',
        'inventory_movements', 'drink_stock_movements', 'payments', 'audit_logs',
        'inventory_items', 'inventory_locations', 'room_types', 'rooms',
        'staff_profiles', 'bookings', 'pos_sessions',
    ];

    public function up(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'sync_deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->timestamp('sync_deleted_at')->nullable()->change();
            });
        }
    }

    public function down(): void
    {
        foreach (self::TABLES as $table) {
            if (! Schema::hasColumn($table, 'sync_deleted_at')) {
                continue;
            }

            Schema::table($table, function (Blueprint $blueprint): void {
                $blueprint->softDeletesTz('sync_deleted_at')->nullable()->change();
            });
        }
    }
};
```

A `->change()` on SQLite requires a table rebuild. If the SQLite grammar cannot
do it in place, wrap each table in a rebuild helper. Test all three engines.

- [ ] **Step 3: Verify**

```powershell
php artisan migrate:fresh --force
php artisan test
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'
php artisan migrate:fresh --force --database=pgsql
php artisan test --filter=SchemaParityTest
```

Expected: `167 DONE` on both engines (166 plus this phase's one migration), parity test green.
Commit: `fix: normalise sync_deleted_at type across all engines`

---

## Task 5: Final verification

- [ ] **Step 1: All three engines migrate fresh**

```powershell
php artisan migrate:fresh --force
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'
php artisan migrate:fresh --force --database=pgsql
```

Expected: `167 DONE, 0 FAIL` on both.

- [ ] **Step 2: Suite green on SQLite, the committed default**

```powershell
$env:DB_CONNECTION='sqlite'; $env:DB_DATABASE=':memory:'
php artisan test
```

Expected: zero failures, zero unexpected skips.

- [ ] **Step 3: Parity gate green**

```powershell
$env:DB_CONNECTION='pgsql'; $env:DB_PORT='5433'
php artisan test --filter=SchemaParityTest
```

- [ ] **Step 4: Record which suite failures are Postgres-specific**

If any test still fails only on Postgres, record it explicitly rather than
letting it look like parity. Document it in `docs/POSTGRES_KNOWN_DIFFERENCES.md`
with the test name and why.

Commit: `chore: record known postgres test differences`

---

## Exit criteria

- `migrate:fresh` completes on SQLite and Postgres.
- The suite passes on both.
- `SchemaParityTest` compares columns, types, nullability, indexes and foreign
  keys, and has been proven to fail on a deliberate divergence.
- The gate runs in CI.
- `sync_deleted_at` is one type on all engines.

Next: [Phase 2R](2026-10-05-phase-2r-part-a-repair-schema-policies.md).
