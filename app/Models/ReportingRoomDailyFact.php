<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingRoomDailyFact extends Model
{
    protected $table = 'reporting_room_daily_facts';
    protected $fillable = [
        'room_id',
        'date',
        'occupied',
        'occupied_hours',
        'guest_count',
        'booking_count',
        'housekeeping_completed',
        'cleaning_duration_minutes',
        'maintenance_issue_count',
        'maintenance_open_count',
        'out_of_service',
        'kitchen_order_count',
        'bar_order_count',
        'laundry_request_count',
        'charges_posted',
        'payments_received',
        'refunds_issued',
        'room_revenue',
        'complaints_count',
        'guest_requests_count',
    ];

    protected function casts(): array
    {
        return [
            'date' => 'date',
            'occupied' => 'boolean',
            'housekeeping_completed' => 'boolean',
            'out_of_service' => 'boolean',
        ];
    }

    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }
}
