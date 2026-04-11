<?php

namespace App\Services;

use App\Models\Charge;
use App\Models\MenuItem;
use App\Models\Order;
use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Str;

class StaffRoomOrderService
{
    public const MANUAL_PAYMENT_METHOD = 'pending_selection';

    public const PAYMENT_METHODS = [
        'pending_selection',
        'room_charge',
        'cash',
        'pos',
        'transfer',
        'online',
    ];

    public const PAYMENT_STATUSES = [
        'pending',
        'paid',
        'failed',
    ];

    public function __construct(
        protected BookingResolverService $bookingResolver,
        protected PaymentAccountingService $paymentAccountingService,
    ) {}

    public function createManualOrder(string $serviceArea, array $data, User $actor): Order
    {
        return DB::transaction(function () use ($serviceArea, $data, $actor) {
            $booking = $this->bookingResolver->resolveActiveBookingForRoom((int) $data['room_id']);

            $items = MenuItem::query()
                ->with(['category:id,name', 'subcategory:id,name'])
                ->where('service_area', $serviceArea)
                ->where('is_available', true)
                ->whereIn('id', Arr::pluck($data['items'], 'menu_item_id'))
                ->get()
                ->keyBy('id');

            if ($items->count() !== count($data['items'])) {
                throw ValidationException::withMessages([
                    'items' => 'One or more selected menu items are unavailable.',
                ]);
            }

            $order = Order::create([
                'booking_id' => $booking->id,
                'room_id' => (int) $data['room_id'],
                'user_id' => $booking->user_id,
                'order_code' => 'ORD-' . strtoupper(Str::random(10)),
                'status' => 'pending',
                'service_area' => $serviceArea,
                'notes' => $data['notes'] ?? null,
                'total' => 0,
                'payment_method' => self::MANUAL_PAYMENT_METHOD,
                'payment_status' => 'pending',
            ]);

            $total = 0;

            foreach ($data['items'] as $line) {
                $menuItem = $items->get((int) $line['menu_item_id']);
                $quantity = (int) $line['quantity'];

                $order->items()->create([
                    'item_name' => $menuItem->name,
                    'qty' => $quantity,
                    'price' => $menuItem->price,
                    'note' => $line['note'] ?? null,
                ]);

                $total += ((float) $menuItem->price * $quantity);
            }

            $order->update(['total' => $total]);

            $this->ensureCharge($order);

            return $order->load([
                'room:id,name',
                'booking:id,booking_code,guest_name',
                'items.menuItem.category:id,name',
                'items.menuItem.subcategory:id,name',
                'charge',
            ]);
        });
    }

    public function updatePayment(Order $order, array $data): Order
    {
        if (($order->payment_status === 'paid' || $order->charge?->status === 'paid')
            && $data['payment_status'] !== 'paid') {
            throw ValidationException::withMessages([
                'payment_status' => 'Paid orders cannot be moved back to an unpaid state from this screen.',
            ]);
        }

        if ($data['payment_status'] === 'paid' && empty($data['payment_method'])) {
            throw ValidationException::withMessages([
                'payment_method' => 'A payment method is required when marking an order as paid.',
            ]);
        }

        return DB::transaction(function () use ($order, $data) {
            $paymentReference = $order->payment_reference;

            if ($data['payment_status'] === 'paid' && blank($paymentReference)) {
                $paymentReference = 'ORDPAY-' . $order->id . '-' . strtoupper(Str::random(8));
            }

            $paymentMethod = $data['payment_method']
                ?? ($data['payment_status'] === 'paid' ? $order->payment_method : self::MANUAL_PAYMENT_METHOD);

            $order->update([
                'payment_method' => $paymentMethod ?: self::MANUAL_PAYMENT_METHOD,
                'payment_status' => $data['payment_status'],
                'payment_reference' => $paymentReference,
            ]);

            $charge = $this->ensureCharge($order->fresh());
            $charge->update([
                'status' => $data['payment_status'] === 'paid' ? 'paid' : 'unpaid',
                'payment_mode' => $this->resolveChargePaymentMode($paymentMethod),
            ]);

            if ($data['payment_status'] === 'paid') {
                $existingPayment = Payment::query()
                    ->where('reference', $paymentReference)
                    ->orWhere('payment_reference', $paymentReference)
                    ->first();

                $wasCompleted = $existingPayment?->status === 'completed';

                $payment = Payment::updateOrCreate(
                    ['reference' => $paymentReference],
                    [
                        'booking_id' => $order->booking_id,
                        'room_id' => $order->room_id,
                        'amount' => $order->total,
                        'amount_paid' => $order->total,
                        'currency' => 'NGN',
                        'method' => $paymentMethod,
                        'payment_reference' => $paymentReference,
                        'transaction_ref' => $paymentReference,
                        'status' => 'completed',
                        'provider' => $paymentMethod === 'online' ? 'online' : 'manual',
                        'payment_type' => 'order',
                        'meta' => [
                            'order_id' => $order->id,
                            'order_code' => $order->order_code,
                            'service_area' => $order->service_area,
                        ],
                        'verified_at' => now(),
                        'paid_at' => now(),
                    ]
                );

                if (! $wasCompleted) {
                    $this->paymentAccountingService->handleSuccessful($payment);
                }
            }

            return $order->fresh()->load([
                'room:id,name',
                'booking:id,booking_code,guest_name',
                'items.menuItem.category:id,name',
                'items.menuItem.subcategory:id,name',
                'charge',
            ]);
        });
    }

    public function ensureCharge(Order $order): Charge
    {
        return Charge::query()->updateOrCreate(
            [
                'billable_type' => $order->getMorphClass(),
                'billable_id' => $order->id,
            ],
            [
                'booking_id' => $order->booking_id,
                'room_id' => $order->room_id,
                'description' => ucfirst($order->service_area) . " Order {$order->order_code}",
                'amount' => $order->total,
                'status' => $order->payment_status === 'paid' ? 'paid' : 'unpaid',
                'payment_mode' => $this->resolveChargePaymentMode($order->payment_method),
                'charge_date' => now()->toDateString(),
                'type' => $order->service_area,
            ]
        );
    }

    protected function resolveChargePaymentMode(?string $paymentMethod): string
    {
        return match ($paymentMethod) {
            'online' => 'prepaid',
            'room_charge' => 'postpaid',
            default => 'postpaid',
        };
    }
}
