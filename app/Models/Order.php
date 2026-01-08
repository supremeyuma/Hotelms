<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Casts\Attribute;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'room_id',
        'user_id',
        'order_code',
        'total',
        'status',
        'service_area',
        'notes',
        'cancelable_until',
        'completed_at',
    ];

    protected $casts = [
        'total' => 'decimal:2',
        'cancelable_until' => 'datetime',
        'completed_at' => 'datetime',
    ];

    protected $appends = ['eta_minutes', 'can_be_cancelled'];

    /* ---------------- Relationships ---------------- */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function items()
    {
        return $this->hasMany(OrderItem::class);
    }

    public function events()
    {
        return $this->hasMany(OrderEvent::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeCompleted($query)
    {
        return $query->where('status', 'completed');
    }


    public function getEtaMinutesAttribute(): int
    {
        return $this->items
            ->map(fn ($item) => $item->menuItem?->effective_prep_time ?? 0)
            ->max() ?? 0;
    }
    
    public function room()
    {
        return $this->belongsTo(Room::class);
    }

    public function canBeCancelled(): Attribute
    {
        return Attribute::get(fn () =>
            $this->cancelable_until && now()->lt($this->cancelable_until)
        );
    }

    public function slaMinutes(): ?int
    {
        if (!$this->completed_at) return null;
        return $this->created_at->diffInMinutes($this->completed_at);
    }
}
