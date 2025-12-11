<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\OrderService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * AutoCloseCompletedOrdersJob
 *
 * Closes orders that have been in delivered state for > X hours.
 */
class AutoCloseCompletedOrdersJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 2;
    public int $timeout = 120;

    public function tags(): array
    {
        return ['orders','auto-close'];
    }

    public function handle(OrderService $orderService, AuditLoggerService $audit)
    {
        $threshold = now()->subHours(24);
        $orders = Order::where('status','delivered')->where('delivered_at','<', $threshold)->get();

        foreach ($orders as $order) {
            $orderService->updateStatus($order, 'completed', null, ['auto' => true]);
            $audit->log('order_auto_completed', $order, $order->id);
        }
    }
}
