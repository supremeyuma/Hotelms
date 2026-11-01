<?php

namespace App\Http\Controllers;

use App\Models\LicenceOrder;
use App\Models\LicencePlan;
use App\Services\Licensing\LicenceCheckoutService;
use App\Services\PaymentProviderManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;
use Throwable;

/**
 * Public licence purchasing.
 *
 * This is the platform's own shop, not the hotel's website: it runs on the
 * licence server and is only reachable when `licensing.checkout_enabled` is on.
 * The price comes from the room-count tier, the buyer picks NGN or USD, and the
 * gateway callback verifies the charge before any key is issued.
 */
class LicenceCheckoutController extends Controller
{
    public function __construct(
        protected LicenceCheckoutService $checkout,
        protected PaymentProviderManager $payments,
    ) {}

    public function pricing(): Response
    {
        return Inertia::render('Licence/Pricing', [
            ...$this->sharedConfiguration(),
            'plans' => $this->planCards(),
        ]);
    }

    public function create(): Response
    {
        abort_unless($this->checkout->enabled(), 404);

        return Inertia::render('Licence/Checkout', [
            ...$this->sharedConfiguration(),
            'plans' => $this->planCards(),
        ]);
    }

    /**
     * Create the order and hand the browser a hosted-checkout URL.
     *
     * JSON rather than an Inertia post: the destination is the gateway, not a
     * page in this app. The form disables its button and shows progress while
     * this runs, so a slow provider never looks like a dead form.
     */
    public function store(Request $request): JsonResponse
    {
        abort_unless($this->checkout->enabled(), 404);

        $data = $request->validate([
            'property_name' => ['required', 'string', 'max:255'],
            'rooms' => ['required', 'integer', 'min:1', 'max:5000'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['required', 'email', 'max:255'],
            'customer_phone' => ['nullable', 'string', 'max:40'],
            'currency' => ['required', 'in:NGN,USD'],
            'provider' => ['nullable', 'string', 'in:flutterwave,paystack'],
        ]);

        $order = $this->checkout->start($data);

        try {
            $initialised = $this->checkout->initialize($order, $data['provider'] ?? null);
        } catch (Throwable $e) {
            $this->checkout->fail($order, $data['provider'] ?? null, ['error' => $e->getMessage()]);

            Log::error('Licence checkout could not start', [
                'order' => $order->reference,
                'error' => $e->getMessage(),
            ]);

            throw ValidationException::withMessages([
                'provider' => $e->getMessage() ?: 'The payment provider is unavailable right now.',
            ]);
        }

        return response()->json([
            'redirect_url' => $initialised['redirect_url'],
            'reference' => $order->reference,
        ]);
    }

    /**
     * Where the gateway sends the buyer back.
     *
     * The gateway's own `tx_ref`/`reference` must match the order before the
     * payment is even looked up: without that, anyone could walk the order ids
     * and have the server verify (and reveal) someone else's purchase. The charge
     * is then verified with the provider, so a forged callback still cannot
     * produce a licence.
     */
    public function callback(Request $request, LicenceOrder $order): RedirectResponse
    {
        abort_unless($this->checkout->enabled(), 404);

        $supplied = (string) (
            $request->query('tx_ref')
            ?? $request->query('reference')
            ?? $request->query('trxref')
            ?? ''
        );

        if ($supplied === '' || ! hash_equals($order->reference, $supplied)) {
            Log::warning('Licence callback reference mismatch', [
                'order' => $order->id,
                'supplied' => $supplied,
            ]);

            return redirect()
                ->route('licence.checkout.failed')
                ->with('error', 'We could not match that payment to this order. If you were charged, contact support with your reference.');
        }

        $status = strtolower((string) $request->query('status', ''));

        if ($status !== '' && ! in_array($status, ['successful', 'success', 'completed'], true)) {
            $this->checkout->fail($order, $order->provider, ['status' => $status]);

            return redirect()
                ->route('licence.checkout.failed')
                ->with('error', 'The payment was not completed. No licence was issued and no money was taken.');
        }

        try {
            $result = $this->checkout->verifyAndComplete($order, $order->provider);
        } catch (Throwable $e) {
            Log::error('Licence payment verification failed', [
                'order' => $order->id,
                'error' => $e->getMessage(),
            ]);

            return redirect()
                ->route('licence.checkout.failed')
                ->with('error', $e->getMessage());
        }

        // Signed so the success page cannot be opened by guessing an order id.
        return redirect(URL::signedRoute('licence.checkout.success', ['order' => $result->id]));
    }

    public function success(Request $request, LicenceOrder $order): Response|RedirectResponse
    {
        abort_unless($this->checkout->enabled(), 404);

        if (! $order->isPaid()) {
            return redirect()
                ->route('licence.checkout.failed')
                ->with('error', 'That order has not been paid yet.');
        }

        // One-time reveal: the key is cleared as it is read, so refreshing the
        // page (or sharing the link) cannot show it a second time.
        $key = $this->checkout->pullKey($order);

        return Inertia::render('Licence/Success', [
            'licenceKey' => $key,
            'order' => [
                'reference' => $order->reference,
                'property_name' => $order->property_name,
                'rooms' => $order->rooms,
                'customer_email' => $order->customer_email,
                'currency' => $order->currency,
                'amount' => (float) $order->amount,
                'term_months' => $order->term_months,
            ],
            'licence' => $order->licence ? [
                'id' => $order->licence->id,
                'plan' => $order->licence->licencePlan?->name,
            ] : null,
            'emailSentTo' => $order->customer_email,
        ]);
    }

    public function failed(Request $request): Response
    {
        return Inertia::render('Licence/Failed', [
            'message' => $request->session()->get('error')
                ?: 'The payment did not complete. You have not been charged for a licence.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function sharedConfiguration(): array
    {
        $providers = collect($this->payments->getEnabledProviders())
            ->map(fn (string $provider) => [
                'value' => $provider,
                'label' => $this->payments->getProviderLabel($provider),
            ])
            ->values()
            ->all();

        return [
            'product' => (string) config('licensing.product', 'MooreLife'),
            'checkoutEnabled' => $this->checkout->enabled(),
            'termMonths' => (int) config('licensing.term_months', 12),
            'defaultCurrency' => (string) config('licensing.currency_default', 'NGN'),
            'providers' => $providers,
            'defaultProvider' => $providers === [] ? null : $this->payments->getDefaultProvider(),
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function planCards(): array
    {
        $months = (int) config('licensing.term_months', 12);

        return LicencePlan::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('min_rooms')
            ->get()
            ->map(fn (LicencePlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'band' => $plan->bandLabel(),
                'min_rooms' => $plan->min_rooms,
                'max_rooms' => $plan->max_rooms,
                'monthly_naira' => (float) $plan->monthly_naira,
                'monthly_usd' => (float) $plan->monthly_usd,
                'term_naira' => $plan->termPrice(LicencePlan::CURRENCY_NAIRA, $months),
                'term_usd' => $plan->termPrice(LicencePlan::CURRENCY_USD, $months),
            ])
            ->values()
            ->all();
    }
}
