<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomAvailabilitySchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'room_availability_schedules';

    protected $fillable = [
        'room_id',
        'room_type_id',
        'property_id',
        'start_date',
        'end_date',
        'reason',
        'is_unavailable',
        'notes',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_unavailable' => 'boolean',
    ];

    /**
     * Get the room that owns this schedule
     */
    public function room(): BelongsTo
    {
        return $this->belongsTo(Room::class);
    }

    /**
     * Get the room type that owns this schedule
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Get the property that owns this schedule
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope to unavailable schedules
     */
    public function scopeUnavailable($query)
    {
        return $query->where('is_unavailable', true);
    }

    /**
     * Scope to schedules within date range
     */
    public function scopeWithinDateRange($query, $startDate, $endDate)
    {
        return $query->where('start_date', '<=', $endDate)
            ->where('end_date', '>=', $startDate);
    }

    /**
     * Check if room is unavailable on specific date
     */
    public function isUnavailableOnDate(?Carbon $date = null): bool
    {
        $checkDate = $date ?? now();
        return $this->is_unavailable && 
            $checkDate >= $this->start_date && 
            $checkDate <= $this->end_date;
    }
}