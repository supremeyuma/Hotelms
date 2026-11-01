<?php

namespace App\Services\Licensing;

use App\Mail\LicenceKeyMail;
use App\Models\Licence;
use App\Models\LicenceOrder;
use App\Models\LicencePlan;
use App\Services\PaymentProviderManager;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Throwable;

/**
 * The buying side of licensing: price a tier, take the money, issue the key.
 *
 * Two entry points can complete a sale — the browser callback and the gateway
 * webhook — in either order, and both may run more than once. `complete()` is
 * therefore the only place that issues a licence, and it is guarded by a row
 * lock plus the order's `licence_id`, so a duplicate notification never sells
 * two licences for one payment.
 */
class LicenceCheckoutService
{
    public function __construct(
        protected LicenceService $licences,
        protected PaymentProviderManager $payments,
    ) {}

    public function enabled(): bool
    {
        return (bool) config('licensing.checkout_enabled');
    }

    /**
     * Price a property from its room count.
     *
     * @return array{plan: LicencePlan, monthly: float, amount: float, months: int, currency: string}
     */
    public function quote(int $rooms, string $currency, ?int $termMonths = null): array
    {
        $currency = $this->normalizeCurrency($currency);
        $months = $termMonths ?? (int) config('licensing.term_months', 12);

        if ($rooms < 1) {
            throw ValidationException::withMessages([
                'rooms' => 'Enter how many rooms the property has.',
            ]);
        }

        $plan = LicencePlan::forRooms($rooms);

        if ($plan === null) {
            throw ValidationException::withMessages([
                'rooms' => 'No licence price covers that room count. Please contact us.',
            ]);
        }

        return [
            'plan' => $plan,
            'monthly' => $plan->monthlyFor($currency),
            'amount' => $plan->termPrice($currency, $months),
            'months' => $months,
            'currency' => $currency,
        ];
    }

    /**
     * Record the order before the buyer is sent to the gateway.
     *
     * The price is computed here from the room count, never taken from the
     * request: a tampered form must not buy a large hotel at small-hotel rates.
     *
     * @param  array{property_name: string, rooms: int, customer_name?: ?string, customer_email: string, customer_phone?: ?string, currency: string, provider?: ?string}  $data
     */
    public function start(array $data): LicenceOrder
    {
        $quote = $this->quote((int) $data['rooms'], (string) $data['currency']);

        return LicenceOrder::create([
            'reference' => 'LIC-' . Str::upper((string) Str::ulid()),
            'licence_plan_id' => $quote['plan']->id,
            'property_name' => trim($data['property_name']),
            'rooms' => (int) $data['rooms'],
            'customer_name' => filled($data['customer_name'] ?? null) ? trim($data['customer_name']) : null,
            'customer_email' => strtolower(trim($data['customer_email'])),
            'customer_phone' => filled($data['customer_phone'] ?? null) ? trim($data['customer_phone']) : null,
            'currency' => $quote['currency'],
            'monthly_amount' => $quote['monthly'],
            'term_months' => $quote['months'],
            'amount' => $quote['amount'],
            'provider' => $data['provider'] ?? null,
            'status' => LicenceOrder::STATUS_PENDING,
        ]);
    }

    /**
     * Open a hosted checkout for an order and return the URL to send the buyer to.
     *
     * @return array{redirect_url: string, provider: string}
     */
    public function initialize(LicenceOrder $order, ?string $provider = null): array
    {
        $provider = $provider ?: $this->payments->getDefaultProvider();

        $result = $this->payments->initializePayment($provider, [
            'email' => $order->customer_email,
            'name' => $order->customer_name ?: $order->property_name,
            'phone_number' => $order->customer_phone,
            'amount' => (float) $order->amount,
            'currency' => $order->currency,
            'reference' => $order->reference,
            'callback_url' => route('licence.checkout.callback', ['order' => $order->id]),
            'description' => "MooreLife licence — {$order->property_name}",
            'metadata' => [
                'licence_order_id' => $order->id,
                'licence_reference' => $order->reference,
                'property' => $order->property_name,
            ],
        ]);

        if (! ($result['success'] ?? false)) {
            throw new RuntimeException(
                $result['error'] ?? 'The payment provider could not start the checkout.'
            );
        }

        $redirect = $result['payment_link']
            ?? $result['authorization_url']
            ?? ($result['data']['link'] ?? null);

        if (! is_string($redirect) || $redirect === '') {
            throw new RuntimeException('The payment provider did not return a checkout URL.');
        }

        $order->forceFill([
            'provider' => $result['provider'] ?? $provider,
            'provider_reference' => $result['reference'] ?? $order->reference,
        ])->save();

        return [
            'redirect_url' => $redirect,
            'provider' => $result['provider'] ?? $provider,
        ];
    }

    /**
     * Confirm with the gateway, then complete. Used by the browser callback.
     */
    public function verifyAndComplete(LicenceOrder $order, ?string $provider = null): LicenceOrder
    {
        $provider = $provider ?: $order->provider;

        $result = $this->payments->verifyPayment($order->reference, $provider);

        if (! ($result['verified'] ?? false)) {
            throw new RuntimeException(
                $result['error'] ?? 'The payment could not be verified as successful.'
            );
        }

        $resolvedProvider = (string) ($result['provider'] ?? $provider ?: $this->payments->getDefaultProvider());

        $this->assertAmountMatches(
            $order,
            $this->normalizeAmount($resolvedProvider, $result['amount'] ?? 0),
            (string) ($result['currency'] ?? $order->currency),
        );

        return $this->complete($order, $resolvedProvider, is_array($result['data'] ?? null) ? $result['data'] : [])['order'];
    }

    /**
     * Issue the licence for a paid order, exactly once.
     *
     * Safe to call from both the callback and the webhook, in any order and any
     * number of times: a second call finds `licence_id` set and returns the
     * existing licence without a key (the key has already been handed out).
     *
     * @param  array<string, mixed>  $gateway
     * @return array{order: LicenceOrder, licence: Licence, key: ?string}
     */
    public function complete(LicenceOrder $order, string $provider, array $gateway = []): array
    {
        $result = DB::transaction(function () use ($order, $provider, $gateway) {
            /** @var LicenceOrder $locked */
            $locked = LicenceOrder::whereKey($order->id)->lockForUpdate()->firstOrFail();

            if ($locked->licence_id !== null) {
                return [
                    'order' => $locked->load('licence'),
                    'licence' => $locked->licence,
                    'key' => null,
                ];
            }

            $plan = $locked->licence_plan_id ? LicencePlan::find($locked->licence_plan_id) : null;

            $issued = $this->licences->issue(
                propertyName: $locked->property_name,
                customerName: $locked->customer_name,
                customerEmail: $locked->customer_email,
                termMonths: $locked->term_months,
                paymentReference: $locked->reference,
                notes: "Sold online via {$provider}.",
                sale: [
                    'plan' => $plan,
                    'rooms' => $locked->rooms,
                    'currency' => $locked->currency,
                    'monthly_amount' => (float) $locked->monthly_amount,
                    'amount' => (float) $locked->amount,
                ],
            );

            $locked->forceFill([
                'status' => LicenceOrder::STATUS_PAID,
                'provider' => $provider,
                'paid_at' => now(),
                'licence_id' => $issued['licence']->id,
                // Held encrypted until the buyer opens the success page, so the
                // key survives a webhook that beats the browser redirect.
                'key_ciphertext' => $issued['key'],
                'meta' => array_filter([
                    ...($locked->meta ?? []),
                    'payment' => $gateway,
                ]),
            ])->save();

            return [
                'order' => $locked,
                'licence' => $issued['licence'],
                'key' => $issued['key'],
            ];
        });

        if ($result['key'] !== null) {
            $this->sendKeyEmail($result['order'], $result['licence'], $result['key']);
        }

        return $result;
    }

    /**
     * Hand the buyer the key once, then forget it.
     *
     * Returns null when the key has already been shown (or the order was
     * completed by something that could not store it), in which case the success
     * page points at the email and support can re-issue.
     */
    public function pullKey(LicenceOrder $order): ?string
    {
        return DB::transaction(function () use ($order) {
            /** @var LicenceOrder|null $locked */
            $locked = LicenceOrder::whereKey($order->id)->lockForUpdate()->first();

            if ($locked === null || $locked->key_ciphertext === null) {
                return null;
            }

            try {
                $key = $locked->key_ciphertext;
            } catch (Throwable $e) {
                // APP_KEY changed after the sale: the key is unrecoverable. Do
                // not fail the page for the buyer; support re-issues.
                Log::warning('Licence order key could not be decrypted', ['order' => $locked->id]);

                $key = null;
            }

            $locked->forceFill(['key_ciphertext' => null])->save();

            return $key;
        });
    }

    public function fail(LicenceOrder $order, ?string $provider = null, array $gateway = []): void
    {
        DB::transaction(function () use ($order, $provider, $gateway) {
            /** @var LicenceOrder|null $locked */
            $locked = LicenceOrder::whereKey($order->id)->lockForUpdate()->first();

            // A webhook may have already completed the sale; never downgrade it.
            if ($locked === null || $locked->licence_id !== null) {
                return;
            }

            $locked->forceFill([
                'status' => LicenceOrder::STATUS_FAILED,
                'provider' => $provider ?: $locked->provider,
                'meta' => array_filter([
                    ...($locked->meta ?? []),
                    'failure' => $gateway,
                ]),
            ])->save();
        });
    }

    public function normalizeAmount(string $provider, mixed $amount): float
    {
        $value = (float) $amount;

        // Paystack states amounts in kobo; Flutterwave in major units.
        return $provider === 'paystack' ? round($value / 100, 2) : round($value, 2);
    }

    public function assertAmountMatches(LicenceOrder $order, float $paid, string $currency): void
    {
        // Gateways can round a cent on conversion; anything larger is a wrong
        // price, a test payment or a tampered checkout.
        if (abs($paid - (float) $order->amount) > 0.01) {
            throw new RuntimeException(
                "The amount paid ({$paid}) does not match the licence price ({$order->amount})."
            );
        }

        $expected = strtoupper($order->currency);
        $actual = strtoupper($currency);

        if ($actual !== '' && $actual !== $expected) {
            throw new RuntimeException(
                "The payment currency ({$actual}) does not match the order ({$expected})."
            );
        }
    }

    private function normalizeCurrency(string $currency): string
    {
        $currency = strtoupper(trim($currency));

        if (! in_array($currency, LicencePlan::CURRENCIES, true)) {
            throw ValidationException::withMessages([
                'currency' => 'Choose either NGN or USD.',
            ]);
        }

        return $currency;
    }

    private function sendKeyEmail(LicenceOrder $order, Licence $licence, string $key): void
    {
        try {
            Mail::to($order->customer_email)->send(new LicenceKeyMail($order, $licence, $key));
        } catch (Throwable $e) {
            // The buyer still sees the key on the success page; a mail outage
            // must not turn a paid order into a failed one.
            Log::error('Licence key email failed', [
                'order' => $order->id,
                'error' => $e->getMessage(),
            ]);
        }
    }
}
