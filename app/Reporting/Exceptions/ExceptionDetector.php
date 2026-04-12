<?php

namespace App\Reporting\Exceptions;

use App\Models\LaundryOrder;
use App\Models\MaintenanceTicket;
use App\Models\Order;
use App\Models\ReportingException;
use Carbon\Carbon;

class ExceptionDetector
{
    /**
     * Check for overdue maintenance tickets
     */
    public static function detectOverdueMaintenanceTickets()
    {
        $threshold = config('reporting.maintenance_sla_hours', 24);
        $cutoffTime = now()->subHours($threshold);

        MaintenanceTicket::where('status', 'open')
            ->where('reported_at', '<', $cutoffTime)
            ->each(function ($ticket) {
                $existing = ReportingException::where('reference_type', 'MaintenanceTicket')
                    ->where('reference_id', $ticket->id)
                    ->where('exception_type', 'maintenance_overdue')
                    ->where('status', 'open')
                    ->first();

                if (! $existing) {
                    ReportingException::createFromReference(
                        'MaintenanceTicket',
                        $ticket->id,
                        'maintenance_overdue',
                        'high',
                        "Maintenance ticket #{$ticket->id} overdue",
                        "Room {$ticket->room_id}: {$ticket->description}",
                        [
                            'room_id' => $ticket->room_id,
                            'reported_at' => $ticket->reported_at,
                            'hours_overdue' => now()->diffInHours($ticket->reported_at),
                        ]
                    );
                }
            });
    }

    /**
     * Check for overdue laundry orders
     */
    public static function detectOverdueLaundryOrders()
    {
        $threshold = config('reporting.laundry_sla_hours', 4);
        $cutoffTime = now()->subHours($threshold);

        LaundryOrder::where('status', '!=', 'delivered')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<', $cutoffTime)
            ->each(function ($order) {
                $existing = ReportingException::where('reference_type', 'LaundryOrder')
                    ->where('reference_id', $order->id)
                    ->where('exception_type', 'laundry_overdue')
                    ->where('status', 'open')
                    ->first();

                if (! $existing) {
                    ReportingException::createFromReference(
                        'LaundryOrder',
                        $order->id,
                        'laundry_overdue',
                        'normal',
                        "Laundry order #{$order->id} processing delayed",
                        "Room {$order->room_id}: Order status: {$order->status}",
                        [
                            'room_id' => $order->room_id,
                            'created_at' => $order->created_at,
                            'hours_overdue' => now()->diffInHours($order->created_at),
                        ]
                    );
                }
            });
    }

    /**
     * Check for kitchen orders delayed beyond SLA
     */
    public static function detectDelayedKitchenOrders()
    {
        $threshold = config('reporting.kitchen_sla_minutes', 45);
        $cutoffTime = now()->subMinutes($threshold);

        Order::where('service_area', 'kitchen')
            ->where('status', '!=', 'delivered')
            ->where('status', '!=', 'cancelled')
            ->where('created_at', '<', $cutoffTime)
            ->each(function ($order) {
                $existing = ReportingException::where('reference_type', 'Order')
                    ->where('reference_id', $order->id)
                    ->where('exception_type', 'kitchen_delayed')
                    ->where('status', 'open')
                    ->first();

                if (! $existing) {
                    ReportingException::createFromReference(
                        'Order',
                        $order->id,
                        'kitchen_delayed',
                        'normal',
                        "Kitchen order #{$order->id} delayed",
                        "Room {$order->room_id}: Status: {$order->status}",
                        [
                            'room_id' => $order->room_id,
                            'service_area' => 'kitchen',
                            'created_at' => $order->created_at,
                            'minutes_delayed' => now()->diffInMinutes($order->created_at),
                        ]
                    );
                }
            });
    }

    /**
     * Detect rooms with repeated issues
     */
    public static function detectRepeatedRoomIssues($roomId, $daysThreshold = 7)
    {
        $complaintCount = ReportingException::where('room_id', $roomId)
            ->where('detected_at', '>=', now()->subDays($daysThreshold))
            ->count();

        $threshold = config('reporting.repeat_issue_threshold', 3);

        if ($complaintCount >= $threshold) {
            $existing = ReportingException::where('room_id', $roomId)
                ->where('exception_type', 'repeated_issues')
                ->where('status', 'open')
                ->first();

            if (! $existing) {
                ReportingException::createFromReference(
                    'Room',
                    $roomId,
                    'repeated_issues',
                    'high',
                    "Room {$roomId} has repeated issues",
                    "Room has {$complaintCount} exceptions in last {$daysThreshold} days",
                    [
                        'room_id' => $roomId,
                        'complaint_count' => $complaintCount,
                        'threshold_days' => $daysThreshold,
                    ]
                );
            }
        }
    }

    /**
     * Detect unresolved maintenance affecting room availability
     */
    public static function detectOutOfServiceMaintenance($roomId)
    {
        $openTickets = MaintenanceTicket::where('room_id', $roomId)
            ->where('status', 'open')
            ->get();

        if ($openTickets->count() > 0) {
            $existing = ReportingException::where('room_id', $roomId)
                ->where('exception_type', 'out_of_service_maintenance')
                ->where('status', 'open')
                ->first();

            if (! $existing) {
                ReportingException::createFromReference(
                    'Room',
                    $roomId,
                    'out_of_service_maintenance',
                    'critical',
                    "Room {$roomId} unavailable due to maintenance",
                    "Room has {$openTickets->count()} open maintenance tickets",
                    ['room_id' => $roomId]
                );
            }
        }
    }

    /**
     * Run all exception detection routines
     */
    public static function runAllDetections()
    {
        self::detectOverdueMaintenanceTickets();
        self::detectOverdueLaundryOrders();
        self::detectDelayedKitchenOrders();
    }
}
