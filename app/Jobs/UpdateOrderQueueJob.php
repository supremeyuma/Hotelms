<?php

namespace App\Jobs;

use App\Services\OrderService;
use App\Models\Order;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * UpdateOrderQueueJob
 *
 * Re-calculates order priorities, assigns unassigned orders to staff or flags if delayed.
 */
class UpdateOrderQueueJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function tags(): array
    {
        return ['orders','queue'];
    }

    public function handle(OrderService $orderService, AuditLoggerService $audit)
    {
        // Example: find pending orders older than 10 minutes and bump priority
        $stale = Order::where('status','pending')->where('created_at','<', now()->subMinutes(10))->get();

        foreach ($stale as $order) {
            // simple heuristic: change status to processing if staff assigned otherwise flag
            if ($order->assigned_staff_id) {
                $orderService->updateStatus($order, 'processing', $order->assigned_staff_id);
            } else {
                // add an event
                $order->events()->create(['event' => 'queue_bumped','meta' => ['reason' => 'stale']]);
                $audit->log('order_queue_bumped', $order, $order->id);
            }
        }
    }
}
