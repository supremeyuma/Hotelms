<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Order extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'user_id',
        'order_code',
        'total',
        'status',
    ];

    protected $casts = [
        'total' => 'decimal:2',
    ];

    protected $appends = ['eta_minutes'];

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
}
