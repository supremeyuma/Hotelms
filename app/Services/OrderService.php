<?php
// app/Services/OrderService.php

namespace App\Services;

use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Models\User;
use App\Models\InventoryItem;
use App\Models\InventoryLog;
use App\Services\AuditLoggerService;
use App\Services\NotificationService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Auth;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Collection;
use App\Enums\OrderStatus;
use Exception;

/**
 * OrderService
 *
 * Encapsulates full order lifecycle: create, update, queues, assign departments, inventory deduction, notifications.
 */
class OrderService
{
    protected AuditLoggerService $audit;
    protected NotificationService $notifier;

    public function __construct(AuditLoggerService $audit, NotificationService $notifier)
    {
        $this->audit = $audit;
        $this->notifier = $notifier;
    }

    /**
     * Create an order and associated items.
     *
     * @param array $payload
     *  - room_id (required)
     *  - user_id (nullable)
     *  - items: [{name, price, quantity, inventory_requirements: [{sku, qty}] }]
     *  - notes
     *  - category/department (optional)
     * @return Order
     * @throws Exception
     */
    public function createOrder(array $payload): Order
    {
        return DB::transaction(function () use ($payload) {
            if (empty($payload['room_id'])) {
                throw new Exception('room_id is required to place an order.');
            }

            // determine department
            $department = $payload['department'] ?? $payload['category'] ?? $this->guessDepartmentFromItems($payload['items'] ?? []);

            $order = Order::create([
                'room_id' => $payload['room_id'],
                'user_id' => $payload['user_id'] ?? null,
                'order_code' => $payload['order_code'] ?? strtoupper('ORD-'.uniqid()),
                'department' => $department,
                'status' => OrderStatus::PENDING,
                'total' => 0,
                'notes' => $payload['notes'] ?? null,
            ]);

            $total = 0;

            foreach ($payload['items'] ?? [] as $it) {
                $qty = intval($it['quantity'] ?? 1);
                $price = floatval($it['price'] ?? 0);
                $subtotal = $qty * $price;

                $line = OrderItem::create([
                    'order_id' => $order->id,
                    'item_name' => $it['name'],
                    'qty' => $qty,
                    'price' => $price,
                ]);

                $total += $subtotal;

                // handle inventory requirements deduction asynchronously or immediately
                if (!empty($it['inventory_requirements']) && is_array($it['inventory_requirements'])) {
                    foreach ($it['inventory_requirements'] as $req) {
                        $this->deductInventory($req['sku'], $req['qty'] * $qty, Auth::id() ?? $payload['user_id'] ?? null);
                    }
                }
            }

            $order->update(['total' => $total]);

            // Notify department staff
            $this->notifier->notifyDepartment($department, "New order #{$order->order_code}", [
                'order_id' => $order->id,
                'room_id' => $order->room_id,
            ]);

            $this->audit->log('order_created', $order, $order->id, ['payload' => $payload]);

            return $order->load('items', 'room');
        });
    }

    /**
     * Update order status with validation and timestamps.
     *
     * @param Order $order
     * @param string $status
     * @param int|null $staffId
     * @param array $meta
     * @return Order
     * @throws Exception
     */
    public function updateStatus(Order $order, string $status, ?int $staffId = null, array $meta = []): Order
    {
        $from = $order->status;
        $to = $status;

        // Validate allowed transitions
        $allowed = $this->allowedTransitions($from);
        if (!in_array($to, $allowed)) {
            throw new Exception("Invalid status transition from {$from} to {$to}.");
        }

        DB::transaction(function () use ($order, $to, $staffId, $meta, $from) {
            $timestamps = [];
            if ($to === OrderStatus::PROCESSING) $timestamps['processing_at'] = now();
            if ($to === OrderStatus::READY) $timestamps['ready_at'] = now();
            if ($to === OrderStatus::DELIVERED) $timestamps['delivered_at'] = now();
            if ($to === OrderStatus::COMPLETED) $timestamps['completed_at'] = now();

            $order->update(array_merge(['status' => $to], $timestamps));

            // Log event on order_events
            $order->events()->create([
                'staff_id' => $staffId,
                'event' => 'status_changed',
                'meta' => array_merge($meta, ['from' => $from, 'to' => $to]),
            ]);

            // notify stakeholders
            $this->notifier->notifyStaff($staffId, "Order #{$order->order_code} changed to {$to}", ['order_id' => $order->id]);

            // If this is a room-service order that was delivered, post a charge to the booking
            if ($to === OrderStatus::DELIVERED) {
                // OrderChargeService will create a Charge record linked to booking/room
                app(\App\Services\OrderChargeService::class)->post($order);
            }

            $this->audit->log('order_status_updated', $order, $order->id, ['from' => $from, 'to' => $to, 'by' => $staffId]);
        });

        return $order->refresh();
    }

    /**
     * Deduct inventory by SKU and create log.
     *
     * @param string $sku
     * @param int $qty
     * @param int|null $staffId
     * @return void
     */
    protected function deductInventory(string $sku, int $qty, ?int $staffId = null): void
    {
        $item = InventoryItem::where('sku', $sku)->first();
        if (!$item) return;

        $before = $item->quantity;
        $item->decrement('quantity', $qty);
        InventoryLog::create([
            'inventory_item_id' => $item->id,
            'staff_id' => $staffId,
            'change' => -abs($qty),
            'meta' => ['before' => $before, 'after' => $item->quantity]
        ]);

        // low-stock notification
        if ($item->quantity <= ($item->threshold ?? 10)) {
            $this->notifier->notifyManagers("Low stock: {$item->name}", ['sku' => $item->sku, 'qty' => $item->quantity]);
            $this->audit->log('inventory_low_alert', $item, $item->id, ['qty' => $item->quantity]);
        }
    }

    /**
     * Guess department by items if not provided.
     *
     * @param array $items
     * @return string
     */
    protected function guessDepartmentFromItems(array $items = []): string
    {
        foreach ($items as $it) {
            $name = strtolower($it['name'] ?? '');
            if (str_contains($name, 'laundry')) return 'laundry';
            if (str_contains($name, 'clean') || str_contains($name, 'towel')) return 'housekeeping';
            if (str_contains($name, 'fix') || str_contains($name, 'repair')) return 'maintenance';
        }
        return 'kitchen';
    }

    /**
     * Allowed transitions mapping.
     *
     * @param string $from
     * @return array
     */
    protected function allowedTransitions(string $from): array
    {
        return match($from) {
            OrderStatus::PENDING => [OrderStatus::PROCESSING, OrderStatus::CANCELLED],
            OrderStatus::PROCESSING => [OrderStatus::READY, OrderStatus::CANCELLED],
            OrderStatus::READY => [OrderStatus::DELIVERED, OrderStatus::CANCELLED],
            OrderStatus::DELIVERED => [OrderStatus::COMPLETED],
            default => [OrderStatus::CANCELLED],
        };
    }

    /**
     * Fetch department queue.
     *
     * @param string $department
     * @param int $perPage
     * @return LengthAwarePaginator
     */
    public function fetchQueue(string $department, int $perPage = 20): LengthAwarePaginator
    {
        return Order::with(['items','room','user'])
            ->where('department', $department)
            ->whereIn('status', [OrderStatus::PENDING, OrderStatus::PROCESSING, OrderStatus::READY])
            ->latest()
            ->paginate($perPage);
    }
}
