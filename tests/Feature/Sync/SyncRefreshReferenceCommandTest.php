<?php

namespace Tests\Feature\Sync;

use App\Models\Sync\SyncNode;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Pins `sync:refresh-reference`, the pull command a freshly installed terminal
 * runs to fetch the reference data sync cannot move.
 *
 * The command walks configured peers in preference order, takes the FIRST
 * answer, applies it through ReferenceSnapshotApplier, and reports honestly:
 * a malformed payload or an all-unreachable peer list exits non-zero, a
 * per-table failure warns but still succeeds, and the ids in the payload are
 * preserved so a snapshotted password keeps authenticating.
 */
class SyncRefreshReferenceCommandTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'sync.enabled' => true,
            'sync.node_id' => 'node-terminal',
            'sync.credential' => 'shared-secret',
        ]);
    }

    /**
     * Seed a local sync_nodes row so the command's preference ranking has a
     * role to read. The column set mirrors NodeRegistrationTest: node_id,
     * name, type, status.
     */
    private function localNode(string $nodeId, string $type): void
    {
        SyncNode::create([
            'node_id' => $nodeId,
            'name' => $nodeId,
            'type' => $type,
            'status' => 'active',
        ]);
    }

    /**
     * A minimal snapshot body carrying one table.
     *
     * @param  array<string, list<array<string, mixed>>>  $tables
     * @return array{generated_at: string, tables: array<string, list<array<string, mixed>>>}
     */
    private function payload(array $tables): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'tables' => $tables,
        ];
    }

    public function test_sync_disabled_warns_and_exits_zero(): void
    {
        config(['sync.enabled' => false]);

        Http::fake();

        $this->artisan('sync:refresh-reference')
            ->expectsOutputToContain('Sync is disabled')
            ->assertExitCode(0);

        Http::assertNothingSent();
    }

    public function test_no_peers_configured_is_an_error(): void
    {
        config(['sync.peers' => []]);

        Http::fake();

        $this->artisan('sync:refresh-reference')
            ->expectsOutputToContain('No peers')
            ->assertExitCode(1);

        Http::assertNothingSent();
    }

    /**
     * @return array<string, array{base_url: string}>
     */
    private function twoPeers(): array
    {
        return [
            'node-cloud' => ['base_url' => 'https://cloud.test'],
            'node-lan' => ['base_url' => 'http://lan.test'],
        ];
    }

    public function test_the_lan_server_is_preferred_and_the_first_answer_wins(): void
    {
        config(['sync.peers' => $this->twoPeers()]);

        // Cloud ranks 1, LAN server ranks 0: the LAN server is dialled first
        // even though it is configured second.
        $this->localNode('node-cloud', 'cloud');
        $this->localNode('node-lan', 'lan_server');

        Http::fake([
            'http://lan.test/*' => Http::response($this->payload([
                'users' => [['id' => 42, 'uuid' => (string) Str::uuid(), 'name' => 'LAN User', 'email' => 'lan@example.test', 'password' => Hash::make('secret')]],
            ]), 200),
            'https://cloud.test/*' => Http::response($this->payload([
                'users' => [['id' => 99, 'uuid' => (string) Str::uuid(), 'name' => 'Cloud User', 'email' => 'cloud@example.test', 'password' => Hash::make('secret')]],
            ]), 200),
        ]);

        $this->artisan('sync:refresh-reference')->assertExitCode(0);

        $sent = Http::recorded();
        $this->assertCount(1, $sent, 'The first answer wins; the fallback peer is never dialled.');
        $this->assertSame('lan.test', parse_url($sent[0][0]->url(), PHP_URL_HOST));

        $this->assertDatabaseHas('users', ['id' => 42, 'email' => 'lan@example.test']);
        $this->assertDatabaseMissing('users', ['id' => 99]);
    }

    public function test_peer_option_matches_the_node_id(): void
    {
        config(['sync.peers' => $this->twoPeers()]);

        $this->localNode('node-cloud', 'cloud');
        $this->localNode('node-lan', 'lan_server');

        Http::fake([
            'https://cloud.test/*' => Http::response($this->payload([
                'users' => [['id' => 99, 'uuid' => (string) Str::uuid(), 'name' => 'Cloud User', 'email' => 'cloud@example.test', 'password' => Hash::make('secret')]],
            ]), 200),
        ]);

        $this->artisan('sync:refresh-reference', ['--peer' => 'node-cloud'])->assertExitCode(0);

        $sent = Http::recorded();
        $this->assertCount(1, $sent);
        $this->assertSame('cloud.test', parse_url($sent[0][0]->url(), PHP_URL_HOST));
        $this->assertDatabaseHas('users', ['id' => 99]);
    }

    public function test_peer_option_matches_the_base_url(): void
    {
        config(['sync.peers' => $this->twoPeers()]);

        $this->localNode('node-cloud', 'cloud');
        $this->localNode('node-lan', 'lan_server');

        Http::fake([
            'https://cloud.test/*' => Http::response($this->payload([
                'users' => [['id' => 99, 'uuid' => (string) Str::uuid(), 'name' => 'Cloud User', 'email' => 'cloud@example.test', 'password' => Hash::make('secret')]],
            ]), 200),
        ]);

        $this->artisan('sync:refresh-reference', ['--peer' => 'https://cloud.test'])->assertExitCode(0);

        $sent = Http::recorded();
        $this->assertCount(1, $sent);
        $this->assertSame('cloud.test', parse_url($sent[0][0]->url(), PHP_URL_HOST));
        $this->assertDatabaseHas('users', ['id' => 99]);
    }

    public function test_an_unreachable_first_peer_falls_through_to_the_next(): void
    {
        config(['sync.peers' => $this->twoPeers()]);

        $this->localNode('node-lan', 'lan_server');
        $this->localNode('node-cloud', 'cloud');

        Http::fake(function (Request $request) {
            if (parse_url($request->url(), PHP_URL_HOST) === 'lan.test') {
                throw new ConnectionException('Connection refused');
            }

            return Http::response($this->payload([
                'users' => [['id' => 42, 'uuid' => (string) Str::uuid(), 'name' => 'Cloud User', 'email' => 'cloud@example.test', 'password' => Hash::make('secret')]],
            ]), 200);
        });

        $this->artisan('sync:refresh-reference')
            ->expectsOutputToContain('unreachable: node-lan')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => 42, 'email' => 'cloud@example.test']);
    }

    public function test_every_peer_unreachable_exits_one_naming_the_peer(): void
    {
        config(['sync.peers' => $this->twoPeers()]);

        $this->localNode('node-lan', 'lan_server');
        $this->localNode('node-cloud', 'cloud');

        Http::fake(fn () => throw new ConnectionException('Connection refused'));

        $this->artisan('sync:refresh-reference')
            ->expectsOutputToContain('node-lan')
            ->assertExitCode(1);
    }

    public function test_nothing_applied_exits_one(): void
    {
        config(['sync.peers' => ['node-lan' => ['base_url' => 'http://lan.test']]]);

        // users.name is NOT NULL, so this row is rejected deterministically
        // with no foreign-key dependency.
        Http::fake([
            'http://lan.test/*' => Http::response($this->payload([
                'users' => [['id' => 1]],
            ]), 200),
        ]);

        $this->artisan('sync:refresh-reference')->assertExitCode(1);

        $this->assertSame(0, DB::table('users')->count());
    }

    public function test_a_partial_failure_still_succeeds_and_warns(): void
    {
        config(['sync.peers' => ['node-lan' => ['base_url' => 'http://lan.test']]]);

        Http::fake([
            'http://lan.test/*' => Http::response($this->payload([
                'users' => [
                    ['id' => 5, 'uuid' => (string) Str::uuid(), 'name' => 'Good User', 'email' => 'good@example.test', 'password' => Hash::make('secret')],
                ],
                // inventory_item_id 999999 does not exist locally: the recipe
                // table points at inventory items that arrive by sync.
                'menu_inventory_recipes' => [
                    ['id' => 1, 'menu_item_id' => 1, 'inventory_item_id' => 999999, 'quantity' => 1],
                ],
            ]), 200),
        ]);

        $this->artisan('sync:refresh-reference')
            ->expectsOutputToContain('failed')
            ->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => 5, 'email' => 'good@example.test']);
    }

    /**
     * The symptom, end to end (spec §Testing): a login works against a
     * snapshotted user, and a staff account added later reaches the terminal
     * without duplicating the first.
     */
    public function test_a_snapshotted_user_can_log_in_and_a_later_staff_account_pulls_without_duplicates(): void
    {
        config(['sync.peers' => ['node-lan' => ['base_url' => 'http://lan.test']]]);

        $hash = Hash::make('secret');

        // Http::fake appends rather than replaces, so a single closure with a
        // flag serves both pulls instead of a second fake stubbing nothing.
        $secondPull = false;

        Http::fake(function () use (&$secondPull, $hash) {
            $users = [
                ['id' => 10, 'uuid' => (string) Str::uuid(), 'name' => 'Ada Okafor', 'email' => 'ada@example.test', 'password' => $hash],
            ];

            if ($secondPull) {
                $users[] = ['id' => 11, 'uuid' => (string) Str::uuid(), 'name' => 'Bola Adeyemi', 'email' => 'bola@example.test', 'password' => Hash::make('secret')];
            }

            return Http::response($this->payload([
                'users' => $users,
                'settings' => [
                    ['id' => 1, 'key' => 'booking.cutoff_minutes', 'value' => '120'],
                ],
            ]), 200);
        });

        $this->artisan('sync:refresh-reference')->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => 10, 'email' => 'ada@example.test']);
        $this->assertSame('10', (string) DB::table('users')->where('email', 'ada@example.test')->value('id'));

        $this->assertTrue(
            Auth::attempt(['email' => 'ada@example.test', 'password' => 'secret']),
            'A login must work against the snapshotted password hash.',
        );

        // A staff account added later at the source arrives on the next pull,
        // and the first user is not duplicated.
        $secondPull = true;

        $this->artisan('sync:refresh-reference')->assertExitCode(0);

        $this->assertSame(2, DB::table('users')->count(), 'No duplicates: the first account is upserted by id.');
        $this->assertDatabaseHas('users', ['id' => 10, 'email' => 'ada@example.test']);
        $this->assertDatabaseHas('users', ['id' => 11, 'email' => 'bola@example.test']);
    }

    public function test_prune_option_deletes_local_rows_the_source_no_longer_has(): void
    {
        config(['sync.peers' => ['node-lan' => ['base_url' => 'http://lan.test']]]);

        $this->assertSame(
            0,
            DB::table('users')->where('email', 'local-only@example.test')->count(),
        );

        // A local-only user the payload does not carry.
        DB::table('users')->insert([
            'id' => 77,
            'uuid' => (string) Str::uuid(),
            'name' => 'Local Only',
            'email' => 'local-only@example.test',
            'password' => Hash::make('secret'),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        Http::fake([
            'http://lan.test/*' => Http::response($this->payload([
                'users' => [
                    ['id' => 10, 'uuid' => (string) Str::uuid(), 'name' => 'Ada Okafor', 'email' => 'ada@example.test', 'password' => Hash::make('secret')],
                ],
            ]), 200),
        ]);

        $this->artisan('sync:refresh-reference', ['--prune' => true])->assertExitCode(0);

        $this->assertDatabaseHas('users', ['id' => 10]);
        $this->assertDatabaseMissing('users', ['id' => 77]);
    }
}
