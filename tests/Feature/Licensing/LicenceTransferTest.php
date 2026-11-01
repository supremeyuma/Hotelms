<?php

namespace Tests\Feature\Licensing;

use App\Models\Licence;
use App\Models\LicenceTransfer;
use App\Services\Licensing\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Customer self-service machine transfer.
 *
 * The point of the feature is that replacing a server no longer needs support.
 * The point of the limit is that a licence still cannot be walked around a group
 * of hotels, so both are asserted here.
 */
class LicenceTransferTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.private_key' => base64_encode(
                file_get_contents(base_path('tests/Fixtures/licensing/private.pem'))
            ),
            'licensing.self_service_transfers' => 2,
        ]);
    }

    public function test_a_customer_can_move_a_licence_to_a_replacement_pc(): void
    {
        $issued = app(LicenceService::class)->issue('Lagos Harbour Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();

        $expiry = $issued['licence']->fresh()->expires_at;

        $response = $this->activate($issued['key'], 'machine-2', transfer: true);

        $response->assertOk()->assertJsonPath('transfers_remaining', 1);

        $licence = $issued['licence']->fresh();

        $this->assertSame('machine-2', $licence->machine_id);
        $this->assertSame('machine-1', $licence->previous_machine_id);
        $this->assertSame(1, $licence->transfer_count);
        $this->assertNotNull($licence->last_transferred_at);

        // The move never restarts or extends the term.
        $this->assertTrue($expiry->equalTo($licence->expires_at));

        // The new token is bound to the new machine.
        $payload = $this->tokenPayload($response->json('token'));
        $this->assertSame('machine-2', $payload['machine_id']);

        // The move is on the record for support.
        $transfer = LicenceTransfer::sole();
        $this->assertSame('machine-1', $transfer->from_machine_id);
        $this->assertSame('machine-2', $transfer->to_machine_id);
    }

    public function test_reactivating_the_same_machine_does_not_use_a_move(): void
    {
        $issued = app(LicenceService::class)->issue('Repeat Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();
        $this->activate($issued['key'], 'machine-1')->assertOk();

        $this->assertSame(0, $issued['licence']->fresh()->transfer_count);
        $this->assertSame(0, LicenceTransfer::count());
    }

    public function test_moving_without_asking_for_a_transfer_is_still_refused(): void
    {
        $issued = app(LicenceService::class)->issue('Careful Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();

        $this->activate($issued['key'], 'machine-2')
            ->assertStatus(422)
            ->assertJsonPath('errors.key.0', fn ($message) => str_contains($message, 'Move my licence to this PC'));

        $this->assertSame('machine-1', $issued['licence']->fresh()->machine_id);
        $this->assertSame(0, $issued['licence']->fresh()->transfer_count);
    }

    public function test_the_allowance_runs_out_and_support_takes_over(): void
    {
        $issued = app(LicenceService::class)->issue('Moving Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();
        $this->activate($issued['key'], 'machine-2', transfer: true)->assertOk();
        $this->activate($issued['key'], 'machine-3', transfer: true)->assertOk();

        $this->activate($issued['key'], 'machine-4', transfer: true)
            ->assertStatus(422)
            ->assertJsonPath('errors.key.0', fn ($message) => str_contains($message, 'self-service moves'));

        $this->assertSame('machine-3', $issued['licence']->fresh()->machine_id);
        $this->assertSame(2, $issued['licence']->fresh()->transfer_count);

        // Support can hand the allowance back without moving the licence.
        app(LicenceService::class)->resetTransfers($issued['licence']->fresh());
        $this->assertSame(0, $issued['licence']->fresh()->transfer_count);

        $this->activate($issued['key'], 'machine-4', transfer: true)->assertOk();
        $this->assertSame('machine-4', $issued['licence']->fresh()->machine_id);
    }

    public function test_the_allowance_is_configurable(): void
    {
        config(['licensing.self_service_transfers' => 0]);

        $issued = app(LicenceService::class)->issue('Locked Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();

        $this->activate($issued['key'], 'machine-2', transfer: true)->assertStatus(422);
    }

    public function test_an_expired_licence_cannot_be_moved(): void
    {
        $issued = app(LicenceService::class)->issue('Expired Hotel');

        $issued['licence']->forceFill([
            'status' => Licence::STATUS_ACTIVE,
            'machine_id' => 'machine-1',
            'activated_at' => Carbon::now()->subYear(),
            'expires_at' => Carbon::now()->subDay(),
        ])->save();

        $this->activate($issued['key'], 'machine-2', transfer: true)->assertStatus(422);
        $this->assertSame('machine-1', $issued['licence']->fresh()->machine_id);
    }

    public function test_a_revoked_licence_cannot_be_moved(): void
    {
        $licences = app(LicenceService::class);
        $issued = $licences->issue('Revoked Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();
        $licences->revoke($issued['licence']->fresh());

        $this->activate($issued['key'], 'machine-2', transfer: true)->assertStatus(422);
        $this->assertSame('machine-1', $issued['licence']->fresh()->machine_id);
    }

    public function test_a_move_records_where_it_came_from(): void
    {
        $issued = app(LicenceService::class)->issue('Audited Hotel');

        $this->activate($issued['key'], 'machine-1')->assertOk();

        $this->withHeader('User-Agent', 'MooreLife desktop')
            ->postJson(route('licence.activate'), [
                'key' => $issued['key'],
                'machine_id' => 'machine-2',
                'transfer' => true,
            ])
            ->assertOk();

        $transfer = LicenceTransfer::sole();

        $this->assertSame('MooreLife desktop', $transfer->user_agent);
        $this->assertNotNull($transfer->ip_address);
    }

    private function activate(string $key, string $machine, bool $transfer = false)
    {
        return $this->postJson(route('licence.activate'), [
            'key' => $key,
            'machine_id' => $machine,
            'transfer' => $transfer,
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function tokenPayload(string $token): array
    {
        [$payload] = explode('.', $token);

        return json_decode(base64_decode(strtr($payload, '-_', '+/')), true);
    }
}
