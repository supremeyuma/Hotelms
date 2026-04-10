<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class DiscountCode extends Model
{
    use HasFactory;

    public const APPLIES_TO_ROOM_RATE = 'room_rate';
    public const APPLIES_TO_KITCHEN = 'kitchen';
    public const APPLIES_TO_BAR = 'bar';
    public const APPLIES_TO_CLUB = 'club';

    public const TYPE_PERCENTAGE = 'percentage';
    public const TYPE_FIXED = 'fixed';

    protected $fillable = [
        'name',
        'code',
        'description',
        'applies_to',
        'discount_type',
        'discount_value',
        'valid_from',
        'valid_until',
        'max_rooms',
        'is_active',
        'created_by',
    ];

    protected $casts = [
        'discount_value' => 'decimal:2',
        'valid_from' => 'datetime',
        'valid_until' => 'datetime',
        'is_active' => 'boolean',
    ];

    public static function scopeOptions(): array
    {
        return [
            self::APPLIES_TO_ROOM_RATE => 'Room fees per night',
            self::APPLIES_TO_KITCHEN => 'Food',
            self::APPLIES_TO_BAR => 'Bar',
            self::APPLIES_TO_CLUB => 'Club',
        ];
    }

    public static function discountTypeOptions(): array
    {
        return [
            self::TYPE_PERCENTAGE => 'Percentage',
            self::TYPE_FIXED => 'Fixed amount',
        ];
    }

    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function redemptions(): HasMany
    {
        return $this->hasMany(DiscountCodeRedemption::class);
    }

    public function getRemainingRoomsAttribute(): ?int
    {
        if ($this->max_rooms === null) {
            return null;
        }

        $usedRooms = (int) $this->redemptions()
            ->whereIn('status', ['reserved', 'applied'])
            ->whereNull('released_at')
            ->sum('rooms_used');

        return max($this->max_rooms - $usedRooms, 0);
    }

    public function getIsCurrentlyValidAttribute(): bool
    {
        $now = now();

        if (! $this->is_active) {
            return false;
        }

        if ($this->valid_from && $now->lt($this->valid_from)) {
            return false;
        }

        if ($this->valid_until && $now->gt($this->valid_until)) {
            return false;
        }

        return true;
    }
}
