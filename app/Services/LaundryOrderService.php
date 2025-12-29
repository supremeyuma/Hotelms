<?php

// app/Services/LaundryOrderService.php

namespace App\Services;

use App\Enums\LaundryStatus;
use App\Models\Charge;
use App\Models\GuestRequest;
use App\Models\LaundryItem;
use App\Models\LaundryOrder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use App\Events\LaundryOrderUpdated;

class LaundryOrderService
{
    /**
     * Create a new laundry order from guest input
     *
     * @param int $roomId
     * @param int $bookingId
     * @param array $items [
     *   ['laundry_item_id' => int, 'quantity' => int],
     *   ...
     * ]
     * @param array $images Array of uploaded file paths
     * @param int $guestId
     * @return LaundryOrder
     */
    public function createOrder(int $roomId, int $bookingId, array $items, array $images = [], int $guestId): LaundryOrder
    {
        return DB::transaction(function () use ($roomId, $bookingId, $items, $images, $guestId) {
            // 1. Generate unique order code
            $orderCode = 'L' . strtoupper(Str::random(8));

            // 2. Calculate total
            $totalAmount = 0;
            $orderItemsData = [];

            foreach ($items as $itemData) {
                $laundryItem = LaundryItem::findOrFail($itemData['laundry_item_id']);
                $quantity = max(1, (int)$itemData['quantity']);
                $subtotal = $laundryItem->price * $quantity;
                $totalAmount += $subtotal;

                $orderItemsData[] = [
                    'laundry_item_id' => $laundryItem->id,
                    'quantity' => $quantity,
                    'unit_price' => $laundryItem->price,
                    'subtotal' => $subtotal,
                ];
            }

            // 3. Create Laundry Order
            $order = LaundryOrder::create([
                'order_code' => $orderCode,
                'room_id' => $roomId,
                'status' => LaundryStatus::REQUESTED,
                'total_amount' => $totalAmount,
            ]);

            event(new LaundryOrderUpdated($order));

            // 4. Create Order Items
            foreach ($orderItemsData as $item) {
                $order->items()->create($item);
            }

            // 5. Save images if any
            foreach ($images as $path) {
                $order->images()->create(['path' => $path]);
            }

            // 6. Create initial status history
            $order->statusHistories()->create([
                'from_status' => null,
                'to_status' => LaundryStatus::REQUESTED->value,
                'changed_by' => $guestId,
            ]);

            // 7. Add pending charge to booking/room
            Charge::create([
                'booking_id' => $bookingId,
                'room_id' => $roomId,
                'amount' => $totalAmount,
                'description' => "Laundry Order: {$orderCode}",
            ]);

            // 8. Create Guest Request for FrontDesk
            GuestRequest::create([
                'requestable_type' => LaundryOrder::class,
                'requestable_id' => $order->id,
                'room_id' => $roomId,
                'type' => 'laundry',
                'status' => LaundryStatus::REQUESTED->value,
                'booking_id' => $bookingId,
            ]);

            return $order;
        });
    }

    /**
     * Change laundry order status
     *
     * @param LaundryOrder $order
     * @param LaundryStatus $newStatus
     * @param int|null $userId
     * @return LaundryOrder
     */
    public function updateStatus(LaundryOrder $order, LaundryStatus $newStatus, ?int $userId = null): LaundryOrder
    {
        return DB::transaction(function () use ($order, $newStatus, $userId) {
            $oldStatus = $order->status->value;

            if ($oldStatus === $newStatus->value) {
                return $order; // no change
            }

            // 1. Update order status
            $order->status = $newStatus;
            $order->save();

            // 2. Record status history
            $order->statusHistories()->create([
                'from_status' => $oldStatus,
                'to_status' => $newStatus->value,
                'changed_by' => $userId,
            ]);

            // 3. Sync Guest Request status
            if ($order->guestRequest) {
                $order->guestRequest->update([
                    'status' => $newStatus->value,
                ]);
            }


            event(new LaundryOrderUpdated($order));

            return $order->fresh();
        });
    }
}
