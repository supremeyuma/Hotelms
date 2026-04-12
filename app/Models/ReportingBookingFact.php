<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ReportingBookingFact extends Model
{
    protected $table = 'reporting_booking_facts';
    protected $fillable = [
        'booking_id',
        'booking_source',
        'status_lifecycle',
        'check_in_date',
        'check_out_date',
        'actual_check_in',
        'actual_check_out',
        'room_nights',
        'guest_count',
        'room_count',
        'room_revenue',
        'ancillary_revenue',
        'total_charges',
        'total_payments',
        'outstanding_balance',
        'complaints_count',
        'service_requests_count',
        'checkout_delay',
        'checkin_delay',
        'requires_follow_up',
        'tags',
    ];

    protected function casts(): array
    {
        return [
            'check_in_date' => 'date',
            'check_out_date' => 'date',
            'actual_check_in' => 'date',
            'actual_check_out' => 'date',
            'checkout_delay' => 'boolean',
            'checkin_delay' => 'boolean',
            'requires_follow_up' => 'boolean',
            'tags' => 'json',
        ];
    }

    public function booking(): BelongsTo
    {
        return $this->belongsTo(Booking::class);
    }
}
