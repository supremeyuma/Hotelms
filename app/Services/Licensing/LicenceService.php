<?php

namespace App\Services\Licensing;

use App\Models\Licence;
use App\Models\LicencePlan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use RuntimeException;

/**
 * Issues and activates licences.
 *
 * The customer receives a short key after payment. The desktop app exchanges it
 * once, online, for a **signed licence file** (payload + RSA-SHA256 signature)
 * that it can verify offline forever after. The private key lives only here, on
 * the licence server.
 */
class LicenceService
{
    /** Unambiguous alphabet: no 0/O, 1/I/L. Read aloud over the phone. */
    private const ALPHABET = 'ABCDEFGHJKMNPQRSTUVWXYZ23456789';

    /**
     * @param  array{plan?: ?LicencePlan, rooms?: ?int, currency?: ?string, monthly_amount?: ?float, amount?: ?float}  $sale
     * @return array{key: string, licence: Licence}
     */
    public function issue(
        string $propertyName,
        ?string $customerName = null,
        ?string $customerEmail = null,
        ?int $termMonths = null,
        ?string $paymentReference = null,
        ?string $notes = null,
        array $sale = [],
    ): array {
        $key = $this->generateKey();

        /** @var LicencePlan|null $plan */
        $plan = $sale['plan'] ?? null;

        $licence = Licence::create([
            'licence_plan_id' => $plan?->id,
            'key_hash' => $this->hashKey($key),
            'key_prefix' => substr($key, 0, 9),
            'property_name' => $propertyName,
            'room_count' => $sale['rooms'] ?? null,
            'customer_name' => $customerName,
            'customer_email' => $customerEmail,
            'plan' => 'annual',
            'term_months' => $termMonths ?? (int) config('licensing.term_months', 12),
            'currency' => $sale['currency'] ?? null,
            'monthly_amount' => $sale['monthly_amount'] ?? null,
            'amount' => $sale['amount'] ?? null,
            'status' => Licence::STATUS_ISSUED,
            'issued_at' => now(),
            'payment_reference' => $paymentReference,
            'notes' => $notes,
        ]);

        return ['key' => $key, 'licence' => $licence];
    }

    /**
     * Replace a licence whose key was lost, or whose sale needs correcting.
     *
     * The new licence carries the same commercial terms and replaces the old one,
     * which is revoked so the lost key cannot be activated on some other PC.
     * The replacement starts its term at its own activation, like any new key.
     *
     * @return array{key: string, licence: Licence}
     */
    public function reissue(Licence $licence): array
    {
        return DB::transaction(function () use ($licence) {
            $licence = Licence::whereKey($licence->id)->lockForUpdate()->firstOrFail();

            $replacement = $this->issue(
                propertyName: $licence->property_name,
                customerName: $licence->customer_name,
                customerEmail: $licence->customer_email,
                termMonths: $licence->term_months,
                paymentReference: $licence->payment_reference,
                notes: trim(($licence->notes ?? '') . "\nReplaces licence #{$licence->id} (key re-issued)."),
                sale: [
                    'plan' => $licence->licencePlan,
                    'rooms' => $licence->room_count,
                    'currency' => $licence->currency,
                    'monthly_amount' => $licence->monthly_amount !== null ? (float) $licence->monthly_amount : null,
                    'amount' => $licence->amount !== null ? (float) $licence->amount : null,
                ],
            );

            $this->revoke($licence);

            return $replacement;
        });
    }

    /**
     * Exchange a key for a signed licence bound to `machineId`.
     *
     * Idempotent for the same machine: restarting an activated install (or
     * reinstalling on the same PC) returns a fresh signed token instead of
     * consuming the key again. A different machine is refused; transferring a
     * licence is a support action that clears the binding first.
     */
    public function activate(string $key, string $machineId): Licence
    {
        $machineId = trim($machineId);

        if ($machineId === '') {
            throw ValidationException::withMessages(['machine_id' => 'A machine id is required.']);
        }

        return DB::transaction(function () use ($key, $machineId) {
            $licence = Licence::where('key_hash', $this->hashKey($key))->lockForUpdate()->first();

            if ($licence === null) {
                throw ValidationException::withMessages(['key' => 'That licence key was not recognised.']);
            }

            if ($licence->status === Licence::STATUS_REVOKED) {
                throw ValidationException::withMessages(['key' => 'That licence has been revoked.']);
            }

            if ($licence->expires_at !== null && $licence->expires_at->isPast()) {
                throw ValidationException::withMessages(['key' => 'That licence has expired.']);
            }

            if ($licence->machine_id !== null && $licence->machine_id !== $machineId) {
                throw ValidationException::withMessages([
                    'key' => 'That licence is already active on another machine.',
                ]);
            }

            $activatedAt = $licence->activated_at ?? now();

            $licence->forceFill([
                'machine_id' => $machineId,
                'status' => Licence::STATUS_ACTIVE,
                'activated_at' => $activatedAt,
                // The term starts when the licence is first activated, not when
                // it was issued: a key bought in advance is not consumed sitting
                // in an inbox.
                'expires_at' => $licence->expires_at ?? $activatedAt->copy()->addMonths($licence->term_months),
            ])->save();

            return $licence->fresh();
        });
    }

    /**
     * The signed licence file the desktop app stores and verifies offline.
     */
    public function signedToken(Licence $licence): string
    {
        if ($licence->machine_id === null || $licence->expires_at === null) {
            throw new RuntimeException('Cannot sign a licence that has not been activated.');
        }

        $payload = [
            'v' => 1,
            'licence_id' => (string) $licence->id,
            'product' => (string) config('licensing.product', 'MooreLife'),
            'property' => $licence->property_name,
            'machine_id' => $licence->machine_id,
            'plan' => $licence->plan,
            'issued_at' => Carbon::now()->toIso8601String(),
            'expires_at' => $licence->expires_at->toIso8601String(),
        ];

        return $this->sign($payload);
    }

    /**
     * @param array<string, mixed> $payload
     */
    public function sign(array $payload): string
    {
        $privateKey = $this->privateKey();
        $json = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        if (! openssl_sign($json, $signature, $privateKey, OPENSSL_ALGO_SHA256)) {
            throw new RuntimeException('Failed to sign the licence payload.');
        }

        return $this->base64UrlEncode($json) . '.' . $this->base64UrlEncode($signature);
    }

    public function revoke(Licence $licence): void
    {
        $licence->forceFill(['status' => Licence::STATUS_REVOKED])->save();
    }

    public function clearMachine(Licence $licence): void
    {
        $licence->forceFill(['machine_id' => null])->save();
    }

    public function generateKey(): string
    {
        $groups = [];

        for ($group = 0; $group < 4; $group++) {
            $chunk = '';
            for ($i = 0; $i < 4; $i++) {
                $chunk .= self::ALPHABET[random_int(0, strlen(self::ALPHABET) - 1)];
            }
            $groups[] = $chunk;
        }

        return 'ML-' . implode('-', $groups);
    }

    public function hashKey(string $key): string
    {
        $normalised = strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $key) ?? '');

        return hash('sha256', $normalised);
    }

    private function privateKey(): \OpenSSLAsymmetricKey
    {
        $encoded = config('licensing.private_key');

        if (blank($encoded)) {
            throw new RuntimeException(
                'Licence signing key is not configured. Set LICENCE_PRIVATE_KEY on the licence server.'
            );
        }

        $pem = base64_decode((string) $encoded, true);

        if ($pem === false) {
            throw new RuntimeException('LICENCE_PRIVATE_KEY is not valid base64.');
        }

        $key = openssl_pkey_get_private($pem);

        if ($key === false) {
            throw new RuntimeException('LICENCE_PRIVATE_KEY is not a valid private key.');
        }

        return $key;
    }

    private function base64UrlEncode(string $value): string
    {
        return rtrim(strtr(base64_encode($value), '+/', '-_'), '=');
    }
}
