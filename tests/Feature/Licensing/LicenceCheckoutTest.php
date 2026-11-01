<?php

namespace Tests\Feature\Licensing;

use App\Mail\LicenceKeyMail;
use App\Models\Licence;
use App\Models\LicenceOrder;
use App\Models\LicencePlan;
use App\Services\Licensing\LicenceCheckoutService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Selling a licence: tier pricing, the gateway hand-off, and the one place that
 * issues a key.
 *
 * The desktop app trusts only a signature, so these tests assert that a licence
 * exists, is priced from the room count and is issued exactly once per payment.
 */
class LicenceCheckoutTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'licensing.private_key' => base64_encode(
                file_get_contents(base_path('tests/Fixtures/licensing/private.pem'))
            ),
            'licensing.checkout_enabled' => true,
            'licensing.term_months' => 12,
            'payment.default' => 'flutterwave',
            'payment.providers' => ['flutterwave' => true, 'paystack' => false],
            'payment.flutterwave.public_key' => 'flw-public',
            'payment.flutterwave.secret_key' => 'flw-secret',
            'payment.flutterwave.secret_hash' => 'flw-hash',
            'payment.paystack.public_key' => null,
            'payment.paystack.secret_key' => null,
        ]);
    }

    public function test_the_pricing_page_shows_the_configured_bands(): void
    {
        $this->get(route('licence.pricing'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Licence/Pricing')
                ->has('plans', 3)
                ->where('plans.0.band', '1–25 rooms')
                ->where('plans.1.band', '26–50 rooms')
                ->where('plans.2.band', '51+ rooms')
                ->where('termMonths', 12));
    }

    public function test_a_property_is_priced_by_its_room_count(): void
    {
        $checkout = app(LicenceCheckoutService::class);

        $this->assertSame(180000.0, $checkout->quote(10, 'NGN')['amount']);
        $this->assertSame(180000.0, $checkout->quote(25, 'NGN')['amount']);
        $this->assertSame(300000.0, $checkout->quote(26, 'NGN')['amount']);
        $this->assertSame(300000.0, $checkout->quote(50, 'NGN')['amount']);
        $this->assertSame(480000.0, $checkout->quote(51, 'NGN')['amount']);

        // The USD list price is separate, not a conversion of the naira one.
        $this->assertSame(600.0, $checkout->quote(10, 'USD')['amount']);
        $this->assertSame(840.0, $checkout->quote(30, 'USD')['amount']);
        $this->assertSame(1200.0, $checkout->quote(200, 'USD')['amount']);
    }

    public function test_a_room_count_the_bands_do_not_cover_is_refused(): void
    {
        LicencePlan::query()->update(['is_active' => false]);

        $this->expectException(\Illuminate\Validation\ValidationException::class);

        app(LicenceCheckoutService::class)->quote(10, 'NGN');
    }

    public function test_the_checkout_page_renders_the_bands_and_providers(): void
    {
        $this->get(route('licence.checkout.create'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Licence/Checkout')
                ->has('plans', 3)
                ->has('providers', 1)
                ->where('providers.0.value', 'flutterwave')
                ->where('defaultProvider', 'flutterwave')
                ->where('termMonths', 12));
    }

    public function test_starting_checkout_records_the_price_and_returns_the_gateway_url(): void
    {
        Http::fake([
            '*/payments' => Http::response([
                'status' => 'success',
                'data' => ['link' => 'https://checkout.flutterwave.com/pay/abc123'],
            ]),
        ]);

        $response = $this->postJson(route('licence.checkout.store'), [
            'property_name' => 'Lagos Harbour Hotel',
            'rooms' => 30,
            'customer_name' => 'Ada Obi',
            'customer_email' => 'ada@example.com',
            'currency' => 'NGN',
            'provider' => 'flutterwave',
            // A price is deliberately not accepted from the client.
            'amount' => 1,
        ]);

        $response->assertOk()->assertJsonPath('redirect_url', 'https://checkout.flutterwave.com/pay/abc123');

        $order = LicenceOrder::sole();

        $this->assertSame(LicenceOrder::STATUS_PENDING, $order->status);
        $this->assertSame(300000.0, (float) $order->amount);
        $this->assertSame(25000.0, (float) $order->monthly_amount);
        $this->assertSame('NGN', $order->currency);
        $this->assertStringStartsWith('LIC-', $order->reference);
        $this->assertSame('flutterwave', $order->provider);
        $this->assertSame('ada@example.com', $order->customer_email);
    }

    public function test_checkout_is_not_available_until_it_is_switched_on(): void
    {
        config(['licensing.checkout_enabled' => false]);

        $this->get(route('licence.checkout.create'))->assertNotFound();
        $this->postJson(route('licence.checkout.store'), [])->assertNotFound();

        // The price list itself stays visible so the page can say "contact us".
        $this->get(route('licence.pricing'))->assertOk();
    }

    public function test_the_callback_verifies_the_charge_and_issues_the_licence(): void
    {
        Mail::fake();

        $order = $this->pendingOrder(rooms: 30);

        Http::fake([
            '*/transactions/verify_by_reference/*' => Http::response([
                'status' => 'success',
                'data' => ['status' => 'successful', 'amount' => 300000, 'currency' => 'NGN'],
            ]),
        ]);

        $response = $this->get(route('licence.checkout.callback', [
            'order' => $order->id,
            'tx_ref' => $order->reference,
            'status' => 'successful',
        ]));

        $response->assertRedirect();

        $order->refresh();

        $this->assertSame(LicenceOrder::STATUS_PAID, $order->status);
        $this->assertNotNull($order->licence_id);
        $this->assertNotNull($order->paid_at);

        $licence = $order->licence;

        $this->assertSame('Lagos Harbour Hotel', $licence->property_name);
        $this->assertSame(30, $licence->room_count);
        $this->assertSame('NGN', $licence->currency);
        $this->assertSame(300000.0, (float) $licence->amount);
        $this->assertSame(Licence::STATUS_ISSUED, $licence->status);
        $this->assertNotNull($licence->licence_plan_id);
        $this->assertSame($order->reference, $licence->payment_reference);

        // The key is emailed and held encrypted until the buyer opens the page.
        Mail::assertSent(LicenceKeyMail::class, fn (LicenceKeyMail $mail) => $mail->hasTo('ada@example.com'));
        $this->assertNotNull($order->fresh()->key_ciphertext);

        // The signed success page reveals the key exactly once.
        $successUrl = $response->headers->get('Location');

        $this->get($successUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Licence/Success')
                ->where('order.reference', $order->reference)
                ->where('licenceKey', fn ($key) => is_string($key) && str_starts_with($key, 'ML-')));

        $this->get($successUrl)
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Licence/Success')
                ->where('licenceKey', null));

        $this->assertNull($order->fresh()->key_ciphertext);
    }

    public function test_a_short_payment_does_not_issue_a_licence(): void
    {
        $order = $this->pendingOrder(rooms: 30);

        Http::fake([
            '*/transactions/verify_by_reference/*' => Http::response([
                'status' => 'success',
                'data' => ['status' => 'successful', 'amount' => 5000, 'currency' => 'NGN'],
            ]),
        ]);

        $this->get(route('licence.checkout.callback', [
            'order' => $order->id,
            'tx_ref' => $order->reference,
            'status' => 'successful',
        ]))->assertRedirect(route('licence.checkout.failed'));

        $this->assertSame(LicenceOrder::STATUS_PENDING, $order->fresh()->status);
        $this->assertNull($order->fresh()->licence_id);
        $this->assertSame(0, Licence::count());
    }

    public function test_a_callback_without_the_matching_reference_is_refused(): void
    {
        Http::fake();

        $order = $this->pendingOrder();

        $this->get(route('licence.checkout.callback', ['order' => $order->id]))
            ->assertRedirect(route('licence.checkout.failed'));

        // Nothing was even verified: guessing an order id reveals nothing.
        Http::assertNothingSent();
        $this->assertSame(LicenceOrder::STATUS_PENDING, $order->fresh()->status);
    }

    public function test_the_success_page_requires_a_signature(): void
    {
        Mail::fake();

        $order = $this->pendingOrder();

        app(LicenceCheckoutService::class)->complete($order->fresh(), 'flutterwave');

        $this->get(route('licence.checkout.success', ['order' => $order->id]))->assertForbidden();
    }

    public function test_the_webhook_completes_a_pending_order_once(): void
    {
        Mail::fake();

        $order = $this->pendingOrder(rooms: 30);

        $payload = [
            'event' => 'charge.completed',
            'data' => [
                'tx_ref' => $order->reference,
                'status' => 'successful',
                'amount' => 300000,
                'currency' => 'NGN',
                'id' => 998877,
            ],
        ];

        $this->withHeader('verif-hash', 'flw-hash')
            ->postJson('/api/webhooks/flutterwave', $payload)
            ->assertOk()
            ->assertJsonPath('status', 'processed');

        $this->assertSame(LicenceOrder::STATUS_PAID, $order->fresh()->status);
        $this->assertNotNull($order->fresh()->licence_id);
        Mail::assertSent(LicenceKeyMail::class);

        // The gateway retries; a second delivery must not sell a second licence.
        $this->withHeader('verif-hash', 'flw-hash')
            ->postJson('/api/webhooks/flutterwave', $payload)
            ->assertOk();

        $this->assertSame(1, Licence::count());
    }

    public function test_a_failed_charge_cancels_the_order_without_issuing(): void
    {
        $order = $this->pendingOrder();

        $this->withHeader('verif-hash', 'flw-hash')
            ->postJson('/api/webhooks/flutterwave', [
                'event' => 'charge.failed',
                'data' => ['tx_ref' => $order->reference, 'status' => 'failed', 'amount' => 300000],
            ])
            ->assertOk();

        $this->assertSame(LicenceOrder::STATUS_FAILED, $order->fresh()->status);
        $this->assertSame(0, Licence::count());
    }

    public function test_paystack_amounts_are_compared_in_major_units(): void
    {
        $checkout = app(LicenceCheckoutService::class);
        $order = $this->pendingOrder(rooms: 30);

        $this->assertSame(300000.0, $checkout->normalizeAmount('paystack', 30000000));
        $this->assertSame(300000.0, $checkout->normalizeAmount('flutterwave', 300000));

        $checkout->assertAmountMatches($order, 300000.0, 'NGN');

        $this->expectException(\RuntimeException::class);
        $checkout->assertAmountMatches($order, 300000.0, 'USD');
    }

    private function pendingOrder(int $rooms = 30): LicenceOrder
    {
        return app(LicenceCheckoutService::class)->start([
            'property_name' => 'Lagos Harbour Hotel',
            'rooms' => $rooms,
            'customer_name' => 'Ada Obi',
            'customer_email' => 'ada@example.com',
            'customer_phone' => '+2348000000000',
            'currency' => 'NGN',
            'provider' => 'flutterwave',
        ]);
    }
}
