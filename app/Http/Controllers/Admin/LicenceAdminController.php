<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Licence;
use App\Models\LicenceOrder;
use App\Models\LicencePlan;
use App\Services\Licensing\LicenceCheckoutService;
use App\Services\Licensing\LicenceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The platform owner's licence desk.
 *
 * Only reachable on the deployment that holds the signing key (the `licence.admin`
 * middleware), and only by the superuser role. It answers the questions a paid
 * product actually generates: who bought, how much, which PC is it bound to,
 * when does it lapse, and what do I do when a customer loses a key or replaces
 * their server.
 */
class LicenceAdminController extends Controller
{
    public function __construct(
        protected LicenceService $licences,
        protected LicenceCheckoutService $checkout,
    ) {}

    public function index(Request $request): Response
    {
        $filters = [
            'q' => trim((string) $request->query('q', '')),
            'status' => (string) $request->query('status', ''),
            'currency' => (string) $request->query('currency', ''),
        ];

        $now = now();

        $query = Licence::query()->with(['licencePlan', 'order'])->latest('id');

        if ($filters['q'] !== '') {
            $term = '%' . $filters['q'] . '%';
            $query->where(function ($builder) use ($term) {
                $builder->where('property_name', 'like', $term)
                    ->orWhere('customer_name', 'like', $term)
                    ->orWhere('customer_email', 'like', $term)
                    ->orWhere('payment_reference', 'like', $term)
                    ->orWhere('key_prefix', 'like', $term);
            });
        }

        if ($filters['currency'] !== '') {
            $query->where('currency', $filters['currency']);
        }

        match ($filters['status']) {
            'active' => $query->where('status', Licence::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now)),
            'expiring' => $query->where('status', Licence::STATUS_ACTIVE)
                ->whereBetween('expires_at', [$now, $now->copy()->addDays(30)]),
            'expired' => $query->where('status', '!=', Licence::STATUS_REVOKED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now),
            'issued' => $query->where('status', Licence::STATUS_ISSUED)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now)),
            'revoked' => $query->where('status', Licence::STATUS_REVOKED),
            default => null,
        };

        $licences = $query->paginate(20)->withQueryString()->through(fn (Licence $licence) => [
            'id' => $licence->id,
            'property_name' => $licence->property_name,
            'customer_name' => $licence->customer_name,
            'customer_email' => $licence->customer_email,
            'plan' => $licence->licencePlan?->name,
            'rooms' => $licence->room_count,
            'currency' => $licence->currency,
            'amount' => $licence->amount !== null ? (float) $licence->amount : null,
            'term_months' => $licence->term_months,
            'status' => $licence->displayStatus(),
            'machine_bound' => $licence->machine_id !== null,
            'machine_id' => $licence->machine_id,
            'transfer_count' => $licence->transfer_count,
            'activated_at' => $licence->activated_at?->toIso8601String(),
            'expires_at' => $licence->expires_at?->toIso8601String(),
            'days_left' => $licence->daysUntilExpiry(),
            'key_prefix' => $licence->key_prefix,
            'payment_reference' => $licence->payment_reference,
            'order_reference' => $licence->order?->reference,
        ]);

        return Inertia::render('Admin/Licences/Index', [
            'licences' => $licences,
            'filters' => $filters,
            'stats' => $this->stats(),
            'recentOrders' => $this->recentOrders(),
            'checkoutEnabled' => $this->checkout->enabled(),
        ]);
    }

    public function show(Licence $licence): Response
    {
        $licence->load(['licencePlan', 'order']);

        $transfers = $licence->transfers()->limit(10)->get()->map(fn ($transfer) => [
            'id' => $transfer->id,
            'from_machine_id' => $transfer->from_machine_id,
            'to_machine_id' => $transfer->to_machine_id,
            'ip_address' => $transfer->ip_address,
            'at' => $transfer->created_at?->toIso8601String(),
        ])->all();

        $replacement = $licence->notes && preg_match('/Replaces licence #(\d+)/', $licence->notes, $m)
            ? (int) $m[1]
            : null;

        return Inertia::render('Admin/Licences/Show', [
            'licence' => [
                'id' => $licence->id,
                'property_name' => $licence->property_name,
                'customer_name' => $licence->customer_name,
                'customer_email' => $licence->customer_email,
                'plan' => $licence->licencePlan?->name,
                'plan_band' => $licence->licencePlan?->bandLabel(),
                'rooms' => $licence->room_count,
                'currency' => $licence->currency,
                'monthly_amount' => $licence->monthly_amount !== null ? (float) $licence->monthly_amount : null,
                'amount' => $licence->amount !== null ? (float) $licence->amount : null,
                'term_months' => $licence->term_months,
                'status' => $licence->displayStatus(),
                'raw_status' => $licence->status,
                'key_prefix' => $licence->key_prefix,
                'machine_id' => $licence->machine_id,
                'previous_machine_id' => $licence->previous_machine_id,
                'transfer_count' => $licence->transfer_count,
                'transfers_remaining' => $licence->transfersRemaining(),
                'transfer_limit' => $licence->transferLimit(),
                'last_transferred_at' => $licence->last_transferred_at?->toIso8601String(),
                'issued_at' => $licence->issued_at?->toIso8601String(),
                'activated_at' => $licence->activated_at?->toIso8601String(),
                'expires_at' => $licence->expires_at?->toIso8601String(),
                'days_left' => $licence->daysUntilExpiry(),
                'payment_reference' => $licence->payment_reference,
                'notes' => $licence->notes,
                'replaces_licence_id' => $replacement,
            ],
            'order' => $licence->order ? [
                'reference' => $licence->order->reference,
                'status' => $licence->order->status,
                'provider' => $licence->order->provider,
                'paid_at' => $licence->order->paid_at?->toIso8601String(),
                'amount' => (float) $licence->order->amount,
                'currency' => $licence->order->currency,
                'rooms' => $licence->order->rooms,
            ] : null,
            // A freshly issued or re-issued key is shown once, after the redirect.
            'issuedKey' => session('licence_key'),
            'transfers' => $transfers,
        ]);
    }

    public function create(): Response
    {
        return Inertia::render('Admin/Licences/Create', [
            'plans' => $this->planOptions(),
            'termMonths' => (int) config('licensing.term_months', 12),
            'currencies' => LicencePlan::CURRENCIES,
            'defaultCurrency' => (string) config('licensing.currency_default', 'NGN'),
        ]);
    }

    /**
     * Issue a key by hand: a bank transfer, a reseller, or a corrected sale.
     */
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'property_name' => ['required', 'string', 'max:255'],
            'rooms' => ['required', 'integer', 'min:1', 'max:5000'],
            'currency' => ['required', Rule::in(LicencePlan::CURRENCIES)],
            'term_months' => ['required', 'integer', 'min:1', 'max:60'],
            'customer_name' => ['nullable', 'string', 'max:255'],
            'customer_email' => ['nullable', 'email', 'max:255'],
            'payment_reference' => ['nullable', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $quote = $this->checkout->quote((int) $data['rooms'], $data['currency'], (int) $data['term_months']);

        $issued = $this->licences->issue(
            propertyName: $data['property_name'],
            customerName: $data['customer_name'] ?? null,
            customerEmail: $data['customer_email'] ?? null,
            termMonths: (int) $data['term_months'],
            paymentReference: $data['payment_reference'] ?? null,
            notes: $data['notes'] ?? null,
            sale: [
                'plan' => $quote['plan'],
                'rooms' => (int) $data['rooms'],
                'currency' => $quote['currency'],
                'monthly_amount' => $quote['monthly'],
                'amount' => $quote['amount'],
            ],
        );

        return redirect()
            ->route('admin.licences.show', $issued['licence'])
            ->with('success', 'Licence issued. Copy the key now — it is not stored and will not be shown again.')
            ->with('licence_key', $issued['key']);
    }

    public function orders(Request $request): Response
    {
        $status = (string) $request->query('status', '');

        $orders = LicenceOrder::query()
            ->with(['plan', 'licence'])
            ->when($status !== '', fn ($query) => $query->where('status', $status))
            ->latest('id')
            ->paginate(25)
            ->withQueryString()
            ->through(fn (LicenceOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'property_name' => $order->property_name,
                'rooms' => $order->rooms,
                'customer_email' => $order->customer_email,
                'currency' => $order->currency,
                'amount' => (float) $order->amount,
                'status' => $order->status,
                'provider' => $order->provider,
                'paid_at' => $order->paid_at?->toIso8601String(),
                'licence_id' => $order->licence_id,
                'created_at' => $order->created_at?->toIso8601String(),
            ]);

        return Inertia::render('Admin/Licences/Orders', [
            'orders' => $orders,
            'status' => $status,
        ]);
    }

    public function revoke(Licence $licence): RedirectResponse
    {
        $this->licences->revoke($licence);

        return back()->with('success', "Licence #{$licence->id} revoked. It can no longer be activated or renewed.");
    }

    public function restore(Licence $licence): RedirectResponse
    {
        // Restoring a revoked licence puts it back to what it was: an activated
        // licence stays active, one that was never used goes back to issued.
        $licence->forceFill([
            'status' => $licence->machine_id !== null ? Licence::STATUS_ACTIVE : Licence::STATUS_ISSUED,
        ])->save();

        return back()->with('success', "Licence #{$licence->id} restored.");
    }

    public function clearMachine(Licence $licence): RedirectResponse
    {
        if ($licence->machine_id === null) {
            return back()->with('error', 'That licence is not bound to a machine.');
        }

        $this->licences->clearMachine($licence);

        return back()->with('success', 'Machine binding cleared. The same key can be activated on the replacement PC; the expiry date is unchanged.');
    }

    public function reissue(Licence $licence): RedirectResponse
    {
        $replacement = $this->licences->reissue($licence);

        return redirect()
            ->route('admin.licences.show', $replacement['licence'])
            ->with('success', "Replacement licence #{$replacement['licence']->id} issued and licence #{$licence->id} revoked. Copy the new key now.")
            ->with('licence_key', $replacement['key']);
    }

    /**
     * Give back the customer's self-service moves (support has verified the
     * request). The binding itself is unchanged; a move still has to happen from
     * the new PC.
     */
    public function resetTransfers(Licence $licence): RedirectResponse
    {
        $this->licences->resetTransfers($licence);

        return back()->with('success', "Self-service moves reset. The customer can move this licence {$licence->fresh()->transferLimit()} more time(s).");
    }

    public function plans(): Response
    {
        return Inertia::render('Admin/Licences/Plans', [
            'plans' => LicencePlan::query()->orderBy('sort_order')->orderBy('min_rooms')->get()->map(fn (LicencePlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'description' => $plan->description,
                'min_rooms' => $plan->min_rooms,
                'max_rooms' => $plan->max_rooms,
                'monthly_naira' => (float) $plan->monthly_naira,
                'monthly_usd' => (float) $plan->monthly_usd,
                'sort_order' => $plan->sort_order,
                'is_active' => $plan->is_active,
            ])->all(),
            'termMonths' => (int) config('licensing.term_months', 12),
            'checkoutEnabled' => $this->checkout->enabled(),
        ]);
    }

    public function updatePlans(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'plans' => ['required', 'array', 'min:1'],
            'plans.*.id' => ['required', 'integer', 'exists:licence_plans,id'],
            'plans.*.name' => ['required', 'string', 'max:255'],
            'plans.*.description' => ['nullable', 'string', 'max:255'],
            'plans.*.min_rooms' => ['required', 'integer', 'min:1', 'max:5000'],
            'plans.*.max_rooms' => ['nullable', 'integer', 'min:1', 'max:5000'],
            'plans.*.monthly_naira' => ['required', 'numeric', 'min:0', 'max:100000000'],
            'plans.*.monthly_usd' => ['required', 'numeric', 'min:0', 'max:1000000'],
            'plans.*.sort_order' => ['required', 'integer', 'min:0', 'max:999'],
            'plans.*.is_active' => ['required', 'boolean'],
        ]);

        DB::transaction(function () use ($data) {
            foreach ($data['plans'] as $row) {
                $plan = LicencePlan::find($row['id']);

                if ($plan === null) {
                    continue;
                }

                $max = $row['max_rooms'] ?? null;

                // A max below min would silently make the tier unreachable.
                if ($max !== null && $max < $row['min_rooms']) {
                    $max = $row['min_rooms'];
                }

                $plan->update([
                    'name' => $row['name'],
                    'description' => $row['description'] ?? null,
                    'min_rooms' => $row['min_rooms'],
                    'max_rooms' => $max,
                    'monthly_naira' => $row['monthly_naira'],
                    'monthly_usd' => $row['monthly_usd'],
                    'sort_order' => $row['sort_order'],
                    'is_active' => $row['is_active'],
                ]);
            }
        });

        return back()->with('success', 'Licence pricing updated. New checkouts use the new prices immediately; existing licences keep the amount they were sold at.');
    }

    /**
     * @return array<string, mixed>
     */
    private function stats(): array
    {
        $now = now();
        $soon = $now->copy()->addDays(30);

        $counts = [
            'active' => Licence::query()
                ->where('status', Licence::STATUS_ACTIVE)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
                ->count(),
            'expiring' => Licence::query()
                ->where('status', Licence::STATUS_ACTIVE)
                ->whereBetween('expires_at', [$now, $soon])
                ->count(),
            'not_activated' => Licence::query()
                ->where('status', Licence::STATUS_ISSUED)
                ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>=', $now))
                ->count(),
            'expired' => Licence::query()
                ->where('status', '!=', Licence::STATUS_REVOKED)
                ->whereNotNull('expires_at')
                ->where('expires_at', '<', $now)
                ->count(),
            'revoked' => Licence::query()->where('status', Licence::STATUS_REVOKED)->count(),
        ];

        // Revenue is kept per currency: adding NGN to USD would be meaningless.
        $revenue = LicenceOrder::query()
            ->where('status', LicenceOrder::STATUS_PAID)
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (float) $total)
            ->all();

        $revenueLast30 = LicenceOrder::query()
            ->where('status', LicenceOrder::STATUS_PAID)
            ->where('paid_at', '>=', $now->copy()->subDays(30))
            ->selectRaw('currency, SUM(amount) as total')
            ->groupBy('currency')
            ->pluck('total', 'currency')
            ->map(fn ($total) => (float) $total)
            ->all();

        return [
            ...$counts,
            'revenue' => $revenue,
            'revenue_last_30_days' => $revenueLast30,
        ];
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function recentOrders(): array
    {
        return LicenceOrder::query()
            ->latest('id')
            ->limit(5)
            ->get()
            ->map(fn (LicenceOrder $order) => [
                'id' => $order->id,
                'reference' => $order->reference,
                'property_name' => $order->property_name,
                'amount' => (float) $order->amount,
                'currency' => $order->currency,
                'status' => $order->status,
                'created_at' => $order->created_at?->toIso8601String(),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function planOptions(): array
    {
        return LicencePlan::query()
            ->active()
            ->orderBy('sort_order')
            ->orderBy('min_rooms')
            ->get()
            ->map(fn (LicencePlan $plan) => [
                'id' => $plan->id,
                'name' => $plan->name,
                'band' => $plan->bandLabel(),
                'min_rooms' => $plan->min_rooms,
                'max_rooms' => $plan->max_rooms,
                'monthly_naira' => (float) $plan->monthly_naira,
                'monthly_usd' => (float) $plan->monthly_usd,
            ])
            ->all();
    }
}
