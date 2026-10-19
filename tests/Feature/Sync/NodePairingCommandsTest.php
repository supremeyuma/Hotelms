<?php

namespace Tests\Feature\Sync;

use App\Models\Property;
use App\Models\Sync\SyncNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The two operator commands that make pairing usable before any admin UI
 * exists: sync:register issues a node credential, sync:pairing-code mints the
 * single-use code a node exchanges for one. Both print secrets once; neither
 * persists a plaintext credential.
 */
class NodePairingCommandsTest extends TestCase
{
    use RefreshDatabase;

    private Property $property;

    protected function setUp(): void
    {
        parent::setUp();

        $this->property = Property::create([
            'name' => 'Cloud Test Hotel',
            'location' => 'Lagos',
            'amenities' => [],
        ]);
    }

    private function runCommand(string $command, array $parameters = []): int
    {
        return Artisan::call($command, $parameters);
    }

    private function commandOutput(): string
    {
        return Artisan::output();
    }

    public function test_sync_register_prints_the_node_id_and_credential_once_and_stores_only_the_hash(): void
    {
        $exit = $this->runCommand('sync:register', [
            '--name' => 'Bar Terminal 1',
            '--type' => 'terminal',
            '--property' => (string) $this->property->id,
        ]);

        // Artisan::output() empties the buffer on read, so read it exactly
        // once per run and reuse the captured string.
        $output = $this->commandOutput();
        $this->assertSame(0, $exit, $output);

        $this->assertStringContainsString('shown once', $output);
        $this->assertSame(1, preg_match('/Node ID:\s+(\S+)/', $output, $idMatch));
        $this->assertSame(1, preg_match('/Credential:\s+(\S+)/', $output, $secretMatch));

        $node = SyncNode::sole();

        // Printed once: the DB keeps only the SHA-256 hash the sync.node
        // middleware authenticates against, never the plaintext.
        $this->assertSame($idMatch[1], $node->node_id);
        $this->assertSame(hash('sha256', $secretMatch[1]), $node->credential);
        $this->assertNotSame($secretMatch[1], $node->credential);

        $this->assertSame('Bar Terminal 1', $node->name);
        $this->assertSame('terminal', $node->type);
        $this->assertSame('active', $node->status);
        $this->assertSame($this->property->id, $node->property_id);
    }

    public function test_sync_register_rejects_an_unknown_property(): void
    {
        $exit = $this->runCommand('sync:register', [
            '--name' => 'Ghost Node',
            '--type' => 'terminal',
            '--property' => '999999',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('does not exist', $this->commandOutput());
        $this->assertSame(0, SyncNode::count());
    }

    public function test_sync_register_rejects_an_unsupported_type_and_missing_options(): void
    {
        $exit = $this->runCommand('sync:register', [
            '--name' => 'Cloud Node',
            '--type' => 'cloud',
            '--property' => (string) $this->property->id,
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('lan_server or terminal', $this->commandOutput());
        $this->assertSame(0, SyncNode::count());

        $exit = $this->runCommand('sync:register', ['--type' => 'terminal']);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('required', $this->commandOutput());
        $this->assertSame(0, SyncNode::count());
    }

    public function test_sync_pairing_code_mints_a_single_use_time_limited_code(): void
    {
        $before = now();

        $exit = $this->runCommand('sync:pairing-code', [
            '--property' => (string) $this->property->id,
            '--ttl' => '15',
        ]);

        $output = $this->commandOutput();
        $this->assertSame(0, $exit, $output);
        $this->assertSame(1, preg_match('/Pairing code:\s+(\S+)/', $output, $match));

        $row = DB::table('sync_pairing_codes')->where('code', $match[1])->first();

        $this->assertNotNull($row);
        $this->assertSame($this->property->id, (int) $row->property_id);
        $this->assertNull($row->used_at);
        $this->assertGreaterThanOrEqual(
            $before->copy()->addMinutes(15)->subSecond(),
            Carbon::parse($row->expires_at)
        );
        $this->assertLessThanOrEqual(
            now()->addMinutes(15)->addMinute(),
            Carbon::parse($row->expires_at)
        );
    }

    public function test_a_minted_code_is_redeemable_through_the_exchange_endpoint(): void
    {
        // The end-to-end loop the brief requires: mint, then exchange.
        $exit = $this->runCommand('sync:pairing-code', [
            '--property' => (string) $this->property->id,
            '--ttl' => '15',
        ]);

        $output = $this->commandOutput();
        $this->assertSame(0, $exit, $output);
        $this->assertSame(1, preg_match('/Pairing code:\s+(\S+)/', $output, $match));

        $response = $this->postJson('/api/sync/register', [
            'property_code' => (string) $this->property->id,
            'pairing_code' => $match[1],
            'node_name' => 'Minted Terminal',
            'node_type' => 'terminal',
        ]);

        $response->assertCreated();
        $this->assertSame(hash('sha256', $response->json('credential')), SyncNode::sole()->credential);
    }

    public function test_sync_pairing_code_rejects_a_bad_ttl_and_unknown_property(): void
    {
        $exit = $this->runCommand('sync:pairing-code', [
            '--property' => (string) $this->property->id,
            '--ttl' => '0',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('--ttl', $this->commandOutput());

        $exit = $this->runCommand('sync:pairing-code', [
            '--property' => '999999',
            '--ttl' => '15',
        ]);

        $this->assertSame(1, $exit);
        $this->assertStringContainsString('does not exist', $this->commandOutput());

        $this->assertSame(0, DB::table('sync_pairing_codes')->count());
    }
}
