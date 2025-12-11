<?php

namespace App\Jobs;

use App\Models\Order;
use App\Services\NotificationService;
use App\Services\AuditLoggerService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

/**
 * SendOrderStatusNotificationJob
 *
 * Notify guest/staff when order status changes.
 */
class SendOrderStatusNotificationJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public Order $order;
    public string $status;
    public int $tries = 3;
    public int $timeout = 60;

    public function __construct(Order $order, string $status)
    {
        $this->order = $order->withoutRelations();
        $this->status = $status;
    }

    public function tags(): array
    {
        return ['order','notification','order:'.$this->order->id];
    }

    public function handle(NotificationService $notifier, AuditLoggerService $audit)
    {
        $order = Order::with('booking','user','room')->find($this->order->id);
        if (! $order) return;

        // Notify staff for delivery and guest for status updates
        $notifier->notifyDepartment($order->department, "Order #{$order->order_code} status: {$this->status}", ['order_id' => $order->id]);
        if ($order->user && $order->user->email) {
            SendGuestMessageJob::dispatch($order->user->email, "Order Update: {$order->order_code}", "Your order status is now {$this->status}.");
        }

        $audit->log('order_status_notification_sent', $order, $order->id, ['status' => $this->status]);
    }
}
