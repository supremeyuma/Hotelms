<?php

namespace Tests\Feature\Sync;

use App\Models\Property;
use App\Models\Sync\SyncNode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * The pairing-code exchange at POST /api/sync/register is the only way into
 * the node registry without a credential, so these tests pin the three
 * properties that make it safe: the code is single-use and time-limited, the
 * credential exists exactly once (only its SHA-256 hash is persisted, the
 * convention AuthenticateSyncNode compares against), and brute-forcing a code
 * is throttled.
 */
class NodeRegistrationTest extends TestCase
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

    private function mintCode(
        ?Property $property = null,
        ?\DateTimeInterface $expiresAt = null,
    ): string {
        $code = strtoupper(bin2hex(random_bytes(6)));

        DB::table('sync_pairing_codes')->insert([
            'property_id' => ($property ?? $this->property)->id,
            'code' => $code,
            'expires_at' => $expiresAt ?? now()->addMinutes(15),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        return $code;
    }

    private function payload(string $code): array
    {
        return [
            'property_code' => (string) $this->property->id,
            'pairing_code' => $code,
            'node_name' => 'Bar Terminal 1',
            'node_type' => 'terminal',
        ];
    }

    public function test_a_pairing_code_is_single_use(): void
    {
        $code = $this->mintCode();

        $first = $this->postJson('/api/sync/register', $this->payload($code));
        $first->assertCreated();

        // The second sequential redemption is the race loser: the code was
        // consumed atomically, so exactly one node may exist.
        $second = $this->postJson('/api/sync/register', $this->payload($code));
        $second->assertStatus(422)->assertJson(['message' => 'Invalid or expired pairing code.']);

        $this->assertSame(1, SyncNode::count());
        $this->assertNotNull(
            DB::table('sync_pairing_codes')->where('code', $code)->value('used_at')
        );
    }

    public function test_a_hand_typed_code_is_normalized_before_lookup(): void
    {
        $code = $this->mintCode();

        // A lower-case, space-padded entry must redeem on every engine:
        // MySQL's ci collation accepts it while sqlite and Postgres would
        // otherwise reject the identical human input.
        $this->postJson('/api/sync/register', $this->payload('  '.strtolower($code).'  '))
            ->assertCreated();

        $this->assertSame(1, SyncNode::count());
    }

    public function test_an_expired_pairing_code_is_rejected(): void
    {
        $code = $this->mintCode(expiresAt: now()->subMinute());

        $this->postJson('/api/sync/register', $this->payload($code))
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired pairing code.']);

        $this->assertSame(0, SyncNode::count());

        // A rejected attempt spends nothing: the code is neither consumed
        // nor extended.
        $this->assertNull(
            DB::table('sync_pairing_codes')->where('code', $code)->value('used_at')
        );
    }

    public function test_an_unknown_pairing_code_is_rejected(): void
    {
        $this->postJson('/api/sync/register', $this->payload('NOSUCHCODE1'))
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired pairing code.']);

        $this->assertSame(0, SyncNode::count());
    }

    public function test_the_credential_is_returned_once_and_stored_hashed(): void
    {
        $code = $this->mintCode();

        $response = $this->postJson('/api/sync/register', $this->payload($code));
        $response->assertCreated();

        $secret = $response->json('credential');
        $this->assertIsString($secret);
        $this->assertNotSame('', $secret);

        // The REAL convention: AuthenticateSyncNode compares
        // hash('sha256', $token) against the stored column.
        $stored = SyncNode::sole()->credential;
        $this->assertNotSame($secret, $stored);
        $this->assertSame(hash('sha256', $secret), $stored);

        // Once means once: a later response never carries the plaintext
        // again, because only the hash exists to serve.
        $retry = $this->postJson('/api/sync/register', $this->payload($code));
        $retry->assertStatus(422);
        $this->assertStringNotContainsString($secret, $retry->getContent());
    }

    public function test_the_issued_credential_authenticates_against_the_sync_api(): void
    {
        $response = $this->postJson('/api/sync/register', $this->payload($this->mintCode()));
        $response->assertCreated();

        // End-to-end proof that the stored hash matches what the sync.node
        // middleware expects: the minted credential must open the real
        // authenticated surface.
        $this->getJson('/api/sync/changes?since=0', [
            'X-Sync-Node-Id' => $response->json('node_id'),
            'Authorization' => 'Bearer '.$response->json('credential'),
        ])->assertOk();
    }

    public function test_the_registered_node_matches_the_real_schema(): void
    {
        $secret = $this->postJson('/api/sync/register', $this->payload($this->mintCode()))
            ->assertCreated()
            ->json('credential');

        $node = SyncNode::sole();

        // Node ids are ULIDs, the same identity shape the desktop mints for
        // SYNC_NODE_ID (desktop envfile.rs).
        $this->assertMatchesRegularExpression('/^[0-9A-HJKMNP-TV-Z]{26}$/', $node->node_id);
        // Real column is `type`, not the plan sketch's `node_type`.
        $this->assertSame('terminal', $node->type);
        $this->assertSame('active', $node->status);
        $this->assertSame($this->property->id, $node->property_id);
        $this->assertSame('Bar Terminal 1', $node->name);
        $this->assertSame(hash('sha256', $secret), $node->credential);
        // Created but never contacted: the middleware stamps last_seen_at on
        // the first authenticated request (AuthenticateSyncNode).
        $this->assertNull($node->last_seen_at);
    }

    public function test_a_pairing_code_is_bound_to_a_single_property(): void
    {
        $other = Property::create([
            'name' => 'Other Hotel',
            'location' => 'Abuja',
            'amenities' => [],
        ]);

        $code = $this->mintCode();

        $wrong = $this->payload($code);
        $wrong['property_code'] = (string) $other->id;

        $this->postJson('/api/sync/register', $wrong)
            ->assertStatus(422)
            ->assertJson(['message' => 'Invalid or expired pairing code.']);

        // The mismatch must not burn the code: the right property can still
        // redeem it afterwards.
        $this->assertSame(0, SyncNode::count());
        $this->postJson('/api/sync/register', $this->payload($code))->assertCreated();
    }

    public function test_an_unsupported_node_type_is_rejected(): void
    {
        $payload = $this->payload($this->mintCode());
        $payload['node_type'] = 'cloud';

        $this->postJson('/api/sync/register', $payload)
            ->assertStatus(422)
            ->assertJsonValidationErrors(['node_type']);

        $this->assertSame(0, SyncNode::count());
    }

    public function test_the_exchange_endpoint_is_throttled(): void
    {
        // Ten attempts per minute per address (routes/api.php), then 429 -
        // brute-forcing a single-use code must be expensive.
        for ($attempt = 1; $attempt <= 10; $attempt++) {
            $this->postJson('/api/sync/register', $this->payload('GUESS'.$attempt))
                ->assertStatus(422);
        }

        $this->postJson('/api/sync/register', $this->payload('GUESS11'))
            ->assertStatus(429);

        $this->assertSame(0, SyncNode::count());
    }
}
