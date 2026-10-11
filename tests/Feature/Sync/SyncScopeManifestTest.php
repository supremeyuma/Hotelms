<?php

namespace Tests\Feature\Sync;

use App\Models\Booking;
use App\Models\InventoryItem;
use App\Models\MenuItem;
use App\Models\PosDocket;
use App\Sync\Policies\AdditivePolicy;
use App\Sync\Policies\ConflictPolicy;
use App\Sync\Policies\LastWriteWinsPolicy;
use App\Sync\Policies\MergePolicy;
use App\Sync\SyncScope;
use App\Sync\SyncScopeResolver;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Keeps config/sync_scope.php minimal, complete and consistent with the policy
 * catalogue.
 *
 * The tripwire proves the manifest covers everything the code reaches. These
 * tests prove the converse and the agreement: no dead entries (a table nothing
 * reaches wastes bandwidth on every sync), every entry has a policy, and each
 * entry's label resolves to the policy class it claims.
 */
class SyncScopeManifestTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Policy label -> policy class. Must stay aligned with SyncScope::CATALOGUE
     * and config/sync_scope.php.
     */
    private const POLICY_BY_LABEL = [
        'merge' => MergePolicy::class,
        'lww' => LastWriteWinsPolicy::class,
        'additive' => AdditivePolicy::class,
        'conflict' => ConflictPolicy::class,
        'push_down' => MergePolicy::class,
    ];

    /** @return list<string> */
    private function reachable(): array
    {
        $resolver = new SyncScopeResolver;
        $tables = [];

        foreach ([PosDocket::class, Booking::class, InventoryItem::class, MenuItem::class] as $model) {
            foreach ($resolver->closureFor($model) as $table) {
                $tables[$table] = true;
            }
        }

        $tables = array_keys($tables);
        sort($tables);

        return $tables;
    }

    public function test_every_manifest_table_has_a_matching_policy(): void
    {
        foreach (array_keys(config('sync_scope.tables')) as $table) {
            $this->assertTrue(
                SyncScope::has($table),
                "Table [{$table}] is in the sync manifest but has no policy in SyncScope::CATALOGUE."
            );
        }
    }

    public function test_policy_classes_match_the_manifest_labels(): void
    {
        foreach (config('sync_scope.tables') as $table => $label) {
            $this->assertArrayHasKey(
                $label,
                self::POLICY_BY_LABEL,
                "config/sync_scope.php labels [{$table}] as [{$label}], which maps to no policy."
            );

            $this->assertInstanceOf(
                self::POLICY_BY_LABEL[$label],
                SyncScope::policyFor($table),
                "Table [{$table}] is labelled [{$label}] but resolved to the wrong policy class."
            );
        }
    }

    public function test_every_manifest_table_is_reachable_or_recorded_as_always_synced(): void
    {
        $always = array_keys(config('sync_scope.always_sync'));

        $neither = array_values(array_diff(
            array_keys(config('sync_scope.tables')),
            $this->reachable(),
            $always
        ));

        $this->assertSame(
            [],
            $neither,
            'Manifest tables are neither reached by a relation nor listed in always_sync: ['
            .implode(', ', $neither).']. Every manifest table must be reachable, or be recorded in '
            .'always_sync with the reason it is reached some other way.'
        );
    }

    public function test_always_sync_tables_all_exist_in_the_policy_catalogue(): void
    {
        foreach (array_keys(config('sync_scope.always_sync')) as $table) {
            $this->assertTrue(SyncScope::has($table), "always_sync table [{$table}] has no registered policy.");
        }
    }

    public function test_read_only_is_a_subset_of_the_terminal_scope(): void
    {
        $tables = array_keys(config('sync_scope.tables'));

        foreach ($this->readOnlyTables() as $table) {
            $this->assertContains($table, $tables, "read_only table [{$table}] is not a manifest table.");
        }
    }

    /** @return list<string> */
    private function readOnlyTables(): array
    {
        return array_values((array) config('sync_scope.read_only'));
    }
}