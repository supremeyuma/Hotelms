<?php

namespace App\Reporting\Projectors;

use App\Models\LaundryOrder;
use App\Models\ReportingEvent;
use App\Models\ReportingLaundryFact;

class LaundryProjector
{
    /**
     * Project laundry order into fact table
     */
    public static function project(LaundryOrder $order)
    {
        $completionMinutes = $order->delivered_at
            ? $order->created_at->diffInMinutes($order->delivered_at)
            : null;

        $slaThreshold = config('reporting.laundry_sla_hours', 4) * 60;
        $delayMinutes = $completionMinutes ? $completionMinutes - $slaThreshold : null;

        $fact = ReportingLaundryFact::updateOrCreate(
            ['laundry_order_id' => $order->id],
            [
                'room_id' => $order->room_id,
                'booking_id' => $order->booking_id,
                'created_at' => $order->created_at,
                'pickup_at' => $order->pickup_at,
                'processing_started_at' => $order->processing_started_at,
                'ready_at' => $order->ready_at,
                'delivered_at' => $order->delivered_at,
                'cancelled_at' => $order->cancelled_at,
                'item_count' => $order->items()->count(),
                'total_amount' => $order->total_amount ?? 0,
                'payment_status' => $order->payment_status ?? 'pending',
                'was_refunded' => $order->refunded ?? false,
                'completion_minutes' => $completionMinutes,
                'delay_minutes' => max(0, $delayMinutes ?? 0),
                'sla_breach' => $delayMinutes && $delayMinutes > 0,
                'status' => $order->status,
            ]
        );

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'laundry.projected',
            'domain' => 'service',
            'department' => 'laundry',
            'room_id' => $order->room_id,
            'booking_id' => $order->booking_id,
            'reference_type' => 'LaundryOrder',
            'reference_id' => $order->id,
            'amount' => $order->total_amount,
        ]);

        return $fact;
    }

    /**
     * Project on status change
     */
    public static function projectOnStatusChange(LaundryOrder $order, $oldStatus)
    {
        self::project($order);

        ReportingEvent::create([
            'occurred_at' => now(),
            'event_type' => 'laundry.status_changed',
            'domain' => 'service',
            'department' => 'laundry',
            'room_id' => $order->room_id,
            'status_before' => $oldStatus,
            'status_after' => $order->status,
            'reference_type' => 'LaundryOrder',
            'reference_id' => $order->id,
        ]);
    }
}
