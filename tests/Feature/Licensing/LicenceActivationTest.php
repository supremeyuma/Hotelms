<?php

namespace Tests\Feature\Licensing;

use App\Models\Licence;
use App\Services\Licensing\LicenceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Licence issuing and activation.
 *
 * The desktop app trusts only a payload whose RSA signature verifies against the
 * public key baked into it, so these tests assert the signature itself, not just
 * the HTTP status.
 */
class LicenceActivationTest extends TestCase
{
    use RefreshDatabase;

    private string $privatePem;

    protected function setUp(): void
    {
        parent::setUp();

        $this->privatePem = file_get_contents(base_path('tests/Fixtures/licensing/private.pem'));
        config(['licensing.private_key' => base64_encode($this->privatePem)]);
    }

    public function test_activating_a_key_returns_a_signed_licence(): void
    {
        $issued = app(LicenceService::class)->issue('Lagos Harbour Hotel');

        $response = $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-abc',
        ]);

        $response->assertOk()->assertJsonStructure(['token', 'property', 'expires_at']);

        [$payload, $verified] = $this->verifyToken($response->json('token'));

        $this->assertTrue($verified, 'the signature must verify with the public key');
        $this->assertSame('Lagos Harbour Hotel', $payload['property']);
        $this->assertSame('machine-abc', $payload['machine_id']);
        $this->assertSame(Licence::STATUS_ACTIVE, $issued['licence']->fresh()->status);

        // One year from activation, not from issue.
        $expires = $issued['licence']->fresh()->expires_at;
        $this->assertTrue(
            $expires->between(now()->addMonths(12)->subDay(), now()->addMonths(12)->addDay()),
            'the term must run twelve months from activation',
        );
    }

    public function test_the_key_is_stored_only_as_a_hash(): void
    {
        $issued = app(LicenceService::class)->issue('Hash Hotel');

        $this->assertDatabaseMissing('licences', ['key_hash' => $issued['key']]);
        $this->assertDatabaseHas('licences', [
            'key_hash' => hash('sha256', str_replace('-', '', $issued['key'])),
        ]);
    }

    public function test_activation_is_idempotent_for_the_same_machine(): void
    {
        $issued = app(LicenceService::class)->issue('Repeat Hotel');

        $first = $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-1',
        ])->assertOk();

        $expiry = $issued['licence']->fresh()->expires_at;

        $second = $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-1',
        ])->assertOk();

        $this->assertTrue($second->json('token') !== '');
        // Re-activating must not extend the term.
        $this->assertTrue($expiry->equalTo($issued['licence']->fresh()->expires_at));
    }

    public function test_a_key_cannot_be_activated_on_a_second_machine(): void
    {
        $issued = app(LicenceService::class)->issue('One Machine Hotel');

        $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-1',
        ])->assertOk();

        $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-2',
        ])->assertStatus(422);
    }

    public function test_an_unknown_key_is_refused(): void
    {
        $this->postJson(route('licence.activate'), [
            'key' => 'ML-AAAA-BBBB-CCCC-DDDD',
            'machine_id' => 'machine-1',
        ])->assertStatus(422);
    }

    public function test_a_revoked_licence_is_refused(): void
    {
        $licences = app(LicenceService::class);
        $issued = $licences->issue('Revoked Hotel');
        $licences->revoke($issued['licence']);

        $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-1',
        ])->assertStatus(422);
    }

    public function test_an_expired_licence_is_refused(): void
    {
        $licences = app(LicenceService::class);
        $issued = $licences->issue('Expired Hotel');

        $issued['licence']->forceFill([
            'status' => Licence::STATUS_ACTIVE,
            'machine_id' => 'machine-1',
            'activated_at' => Carbon::now()->subYear(),
            'expires_at' => Carbon::now()->subDay(),
        ])->save();

        $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-1',
        ])->assertStatus(422);
    }

    public function test_activation_fails_loudly_when_signing_is_not_configured(): void
    {
        config(['licensing.private_key' => null]);

        $issued = app(LicenceService::class)->issue('Unsigned Hotel');

        $this->postJson(route('licence.activate'), [
            'key' => $issued['key'],
            'machine_id' => 'machine-1',
        ])->assertStatus(503);
    }

    /**
     * Split the token and verify it exactly as the desktop app does.
     *
     * @return array{0: array<string, mixed>, 1: bool}
     */
    private function verifyToken(string $token): array
    {
        [$encodedPayload, $encodedSignature] = explode('.', $token);

        $payload = base64_decode(strtr($encodedPayload, '-_', '+/'), true);
        $signature = base64_decode(strtr($encodedSignature, '-_', '+/'), true);

        $publicPem = file_get_contents(base_path('tests/Fixtures/licensing/public.pem'));
        $publicKey = openssl_pkey_get_public($publicPem);

        $verified = openssl_verify($payload, $signature, $publicKey, OPENSSL_ALGO_SHA256) === 1;

        return [json_decode($payload, true), $verified];
    }
}
