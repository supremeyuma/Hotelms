<?php

namespace App\Services;

use App\Enums\LaundryStatus;
use App\Models\Charge;
use App\Models\GuestRequest;
use App\Models\LaundryItem;
use App\Models\LaundryOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\LaundryOrderUpdated;
use App\Services\LaundryAccountingService;
use App\Services\PricingService;

class LaundryOrderService
{
    public function __construct(
        private LaundryAccountingService $accountingService,
        private PricingService $pricingService
    ) {}

    /**
     * Create a new laundry order from guest input
     */
    public function createOrder(
        int $roomId,
        int $bookingId,
        array $items,
        array $images = [],
        ?int $guestId = null,
        string $paymentMode = 'prepaid' // 🔑 default matches Kitchen
    ): LaundryOrder {
        return DB::transaction(function () use (
            $roomId,
            $bookingId,
            $items,
            $images,
            $guestId,
            $paymentMode
        ) {
            // 1️⃣ Generate unique order code
            $orderCode = 'L' . strtoupper(Str::random(8));

            // 2️⃣ Calculate total
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($items as $itemData) {
                $laundryItem = LaundryItem::findOrFail($itemData['laundry_item_id']);
                $quantity = max(1, (int) $itemData['quantity']);
                $subtotal = $laundryItem->price * $quantity;

                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'laundry_item_id' => $laundryItem->id,
                    'quantity'        => $quantity,
                    'unit_price'      => $laundryItem->price,
                    'subtotal'        => $subtotal,
                ];
            }

            // Calculate final total with 7.5% VAT and 1% service charge for laundry
            $pricing = $this->pricingService->calculatePricing($totalAmount, 0.075, 0.01);
            $finalTotal = $pricing['total'];

            // 3️⃣ Create Laundry Order
            $order = LaundryOrder::create([
                'order_code'    => $orderCode,
                'room_id'       => $roomId,
                'status'        => LaundryStatus::REQUESTED,
                'total_amount'  => $finalTotal,
            ]);

            // 4️⃣ Create Order Items
            foreach ($orderItemsData as $item) {
                $order->items()->create($item);
            }

            // 5️⃣ Save images if any
            foreach ($images as $path) {
                $order->images()->create(['path' => $path]);
            }

            // 6️⃣ Create initial status history
            $order->statusHistories()->create([
                'from_status' => null,
                'to_status'   => LaundryStatus::REQUESTED->value,
                'changed_by'  => $guestId,
            ]);

            /**
             * 7️⃣ CREATE CHARGE (THIS IS THE KEY PART)
             * ---------------------------------------
             * - Linked directly to the laundry order
             * - Has status + payment_mode
             * - Enforced by staff UI + backend
             */
            $order->charge()->create([
                //'order_id'     => $order->id,
                'booking_id'   => $bookingId,
                'room_id'      => $roomId,
                'amount'       => $finalTotal,
                'status'       => 'unpaid',
                'payment_mode' => $paymentMode, // prepaid | pay_on_delivery | postpaid
                'type'         => 'laundry',
                'description'  => "Laundry Order ({$orderCode})",
                'charge_date'  => now(),
            ]);

            // 8️⃣ Create Guest Request for Front Desk
            GuestRequest::create([
                'requestable_type' => LaundryOrder::class,
                'requestable_id'   => $order->id,
                'room_id'          => $roomId,
                'type'             => 'laundry',
                'status'           => LaundryStatus::REQUESTED->value,
                'booking_id'       => $bookingId,
            ]);

            event(new LaundryOrderUpdated($order));

            return $order;
        });
    }

    /**
     * Change laundry order status
     */
    public function updateStatus(
        LaundryOrder $order,
        LaundryStatus $newStatus,
        ?int $userId = null
    ): LaundryOrder {
        return DB::transaction(function () use ($order, $newStatus, $userId) {
            $oldStatus = $order->status->value;

            if ($oldStatus === $newStatus->value) {
                return $order;
            }

            // 🔒 HARD PAYMENT GUARD (same as Kitchen)
            if (
                $order->charge &&
                $order->charge->payment_mode === 'prepaid' &&
                $order->charge->status === 'unpaid'
            ) {
                abort(
                    403,
                    'Laundry order cannot be processed until payment is completed.'
                );
            }

            // 1️⃣ Update order status
            $order->status = $newStatus;
            $order->save();

            // 2️⃣ Record status history
            $order->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status'   => $newStatus->value,
                'changed_by'  => $userId,
            ]);

            // 3️⃣ Sync Guest Request status
            if ($order->guestRequest) {
                $order->guestRequest->update([
                    'status' => $newStatus->value,
                ]);
            }

            if ($order->wasChanged('status') && $order->status === 'completed') {
                $this->accountingService->handleCompleted($order);
            }


            event(new LaundryOrderUpdated($order));

            return $order->fresh(['charge']);
        });
    }
}
