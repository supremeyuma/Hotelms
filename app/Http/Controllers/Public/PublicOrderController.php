<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Services\PaymentProviderManager;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Inertia\Inertia;

class PublicOrderController extends Controller
{
    public function __construct(
        protected PaymentProviderManager $paymentManager,
    ) {}

    /**
     * Store a new online order from public customers
     * Prepaid payment only
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department'      => 'required|in:kitchen,bar',
            'items'           => 'required|array|min:1',
            'items.*.name'    => 'required|string|max:255',
            'items.*.price'   => 'required|numeric|min:0',
            'items.*.quantity'=> 'required|integer|min:1',
            'items.*.note'    => 'nullable|string|max:255',
        ]);

        try {
            $order = DB::transaction(function () use ($validated) {
                // Create order for public/anonymous user
                $order = Order::create([
                    'department'      => $validated['department'],
                    'status'          => OrderStatus::PENDING,
                    'service_area'    => $validated['department'],
                    'payment_method'  => 'online', // Prepaid only for public orders
                    'payment_status'  => 'pending',
                    'order_code'      => strtoupper('PUB-' . uniqid()),
                    'total'           => 0,
                    'user_id'         => Auth::check() ? Auth::id() : null,
                ]);

                $total = 0;

                // Add order items
                foreach ($validated['items'] as $item) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'item_name'  => $item['name'],
                        'price'      => $item['price'],
                        'qty'        => $item['quantity'],
                        'note'       => $item['note'] ?? null,
                    ]);

                    $total += $item['price'] * $item['quantity'];
                }

                $order->update(['total' => $total]);

                // TODO: Implement audit logging once AuditLogger is properly set up
                // AuditLoggerService::log('public_order_created', $order);

                return $order;
            });

            return redirect()->route('menu.online.show', ['type' => $validated['department']])
                ->with('success', "Order {$order->order_code} created. Payment can now be completed.");

        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to place order. Please try again.'
            );
        }
    }

    public function paymentCallback(Request $request, Order $order)
    {
        $reference = $request->input('tx_ref') ?? $request->input('reference') ?? $order->order_code;
        $provider = strtolower((string) ($request->input('provider') ?? config('payment.default', 'flutterwave')));

        try {
            $verification = $this->paymentManager->verifyPayment($reference, $provider);

            if (! ($verification['success'] ?? false) || ! ($verification['verified'] ?? false)) {
                $order->update([
                    'payment_status' => 'pending',
                    'payment_reference' => $reference,
                ]);

                return redirect()->route('public.orders.payment.failed', ['order' => $order->id, 'reference' => $reference])
                    ->with('error', 'We could not confirm your payment yet.');
            }

            $resolvedProvider = $verification['provider'] ?? $provider;

            $order->update([
                'payment_status' => 'paid',
                'payment_method' => $resolvedProvider,
                'payment_reference' => $reference,
            ]);

            return redirect()->route('public.orders.payment.success', ['order' => $order->id, 'reference' => $reference])
                ->with('success', 'Payment confirmed successfully.');
        } catch (\Throwable $e) {
            report($e);

            return redirect()->route('public.orders.payment.failed', ['order' => $order->id, 'reference' => $reference])
                ->with('error', 'We could not confirm your payment right now.');
        }
    }

    public function paymentSuccess(Request $request, Order $order)
    {
        return Inertia::render('Public/OrderPaymentResult', [
            'order' => $order->load('items'),
            'status' => 'success',
            'reference' => $request->query('reference') ?? $order->payment_reference,
            'message' => session('success', 'Payment confirmed successfully.'),
        ]);
    }

    public function paymentFailed(Request $request, Order $order)
    {
        return Inertia::render('Public/OrderPaymentResult', [
            'order' => $order->load('items'),
            'status' => 'failed',
            'reference' => $request->query('reference') ?? $order->payment_reference,
            'message' => session('error', 'Payment was not confirmed.'),
        ]);
    }
}
