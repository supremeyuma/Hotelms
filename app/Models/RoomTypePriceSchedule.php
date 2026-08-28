<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class RoomTypePriceSchedule extends Model
{
    use HasFactory, SoftDeletes;

    protected $table = 'room_type_price_schedules';

    protected $fillable = [
        'room_type_id',
        'property_id',
        'start_date',
        'end_date',
        'custom_price',
        'description',
        'is_active',
    ];

    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'custom_price' => 'decimal:2',
        'is_active' => 'boolean',
    ];

    /**
     * Get the room type that owns this price schedule
     */
    public function roomType(): BelongsTo
    {
        return $this->belongsTo(RoomType::class);
    }

    /**
     * Get the property that owns this price schedule
     */
    public function property(): BelongsTo
    {
        return $this->belongsTo(Property::class);
    }

    /**
     * Scope to active schedules
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
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
     * Get the effective price for a specific date
     */
    public function getPriceForDate(?Carbon $date = null): float
    {
        $checkDate = $date ?? now();
        if ($checkDate < $this->start_date || $checkDate > $this->end_date) {
            return $this->roomType->base_price;
        }
        return $this->custom_price;
    }
}