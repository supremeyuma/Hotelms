<?php

namespace App\Reporting\Projectors;

use App\Models\Order;
use App\Models\ReportingEvent;
use App\Models\ReportingOrderFact;

class OrderProjector
{
    /**
     * Project order into fact table
     */
    public static function project(Order $order)
    {
        $completionMinutes = $order->delivered_at
            ? $order->created_at->diffInMinutes($order->delivered_at)
            : null;

        $delayMinutes = $completionMinutes
            ? $completionMinutes - ($order->service_area === 'kitchen' ? (config('reporting.kitchen_sla_minutes', 45)) : 30)
            : null;

        $fact = ReportingOrderFact::updateOrCreate(
            ['order_id' => $order->id],
            [
                'service_area' => $order->service_area ?? 'kitchen',
                'created_at' => $order->created_at,
                'accepted_at' => $order->accepted_at,
                'prepared_at' => $order->prepared_at,
                'delivered_at' => $order->delivered_at,
                'cancelled_at' => $order->cancelled_at,
                'room_id' => $order->room_id,
                'booking_id' => $order->booking_id,
                'staff_owner_id' => $order->staff_owner_id,
                'amount' => $order->total_amount ?? 0,
                'payment_status' => $order->payment_status ?? 'pending',
                'was_refunded' => $order->refunded ?? false,
                'refund_amount' => $order->refund_amount,
                'completion_minutes' => $completionMinutes,
                'delay_minutes' => max(0, $delayMinutes ?? 0),
                'status' => $order->status,
                'sla_breached' => $delayMinutes && $delayMinutes > 0,
            ]
        );

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'order.projected',
            'domain' => 'service',
            'department' => $order->service_area,
            'room_id' => $order->room_id,
            'booking_id' => $order->booking_id,
            'reference_type' => 'Order',
            'reference_id' => $order->id,
            'amount' => $order->total_amount,
        ]);

        return $fact;
    }

    /**
     * Project on status change
     */
    public static function projectOnStatusChange(Order $order, $oldStatus)
    {
        self::project($order);

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'order.status_changed',
            'domain' => 'service',
            'department' => $order->service_area,
            'room_id' => $order->room_id,
            'status_before' => $oldStatus,
            'status_after' => $order->status,
            'reference_type' => 'Order',
            'reference_id' => $order->id,
        ]);
    }
}
