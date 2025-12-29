<?php

namespace App\Services;

use App\Models\Order;
use App\Models\GuestRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use App\Events\OrderStatusUpdated;
use App\Services\AuditLogger;
use Illuminate\Validation\ValidationException;

class OrderWorkflowService
{
    /**
     * Centralized status transitions per service type
     */
    protected array $transitions = [
        'laundry' => [
            'requested' => ['pickup_scheduled', 'cancelled'],
            'pickup_scheduled' => ['picked_up', 'cancelled'],
            'picked_up' => ['washing'],
            'washing' => ['ready'],
            'ready' => ['delivered'],
            'delivered' => [],
        ],

        'kitchen' => [
            'pending' => ['in_progress', 'cancelled'],
            'in_progress' => ['ready'],
            'ready' => ['delivered'],
            'delivered' => [],
        ],

        'bar' => [
            'pending' => ['preparing', 'cancelled'],
            'preparing' => ['ready'],
            'ready' => ['delivered'],
            'delivered' => [],
        ],

        'cleaning' => [
            'requested' => ['assigned', 'cancelled'],
            'assigned' => ['in_progress'],
            'in_progress' => ['completed'],
            'completed' => [],
        ],
    ];

    /**
     * Update order status safely
     */
    public function updateStatus(
        Order $order,
        string $newStatus,
        ?int $actorId = null,
        ?string $eventOverride = null
    ): Order {
        return DB::transaction(function () use ($order, $newStatus, $actorId, $eventOverride) {
            $service = $order->type; // laundry | kitchen | bar | cleaning
            $current = $order->status;

            if (! isset($this->transitions[$service])) {
                throw ValidationException::withMessages([
                    'service' => "Unsupported service type [$service]"
                ]);
            }

            if (! in_array(
                $newStatus,
                $this->transitions[$service][$current] ?? [],
                true
            )) {
                throw ValidationException::withMessages([
                    'status' => "Invalid status transition: $current → $newStatus"
                ]);
            }

            // 1. Update Order
            $order->update([
                'status' => $newStatus,
            ]);

            // 2. Record Order Event
            $order->events()->create([
                'staff_id' => $actorId,
                'event' => $eventOverride ?? 'status_changed',
                'meta' => [
                    'from' => $current,
                    'to' => $newStatus,
                ],
            ]);

            // 3. Sync Guest Request (CRITICAL)
            $this->syncGuestRequest($order, $newStatus);

            // 4. Audit
            AuditLogger::log(
                'order_status_updated',
                'Order',
                $order->id,
                [
                    'service' => $service,
                    'from' => $current,
                    'to' => $newStatus,
                    'by' => $actorId,
                ]
            );

            // 5. Broadcast
            Event::dispatch(new OrderStatusUpdated($order->fresh()));

            return $order->fresh();
        });
    }

    /**
     * GuestRequest visibility + syncing logic
     */
    protected function syncGuestRequest(Order $order, string $status): void
    {
        $guestRequest = $order->guestRequest;

        if (! $guestRequest) {
            return;
        }

        $guestRequest->update([
            'status' => $status,
        ]);

        /**
         * FrontDesk visibility rules:
         * - Laundry: visible until pickup_scheduled
         * - Kitchen/Bar: visible until in_progress
         * - Cleaning: visible until assigned
         */
        $hideAt = match ($order->type) {
            'laundry' => 'pickup_scheduled',
            'kitchen', 'bar' => 'in_progress',
            'cleaning' => 'assigned',
            default => null,
        };

        if ($hideAt && $status === $hideAt) {
            $guestRequest->update([
                'resolved_at' => now(),
            ]);
        }
    }
}
