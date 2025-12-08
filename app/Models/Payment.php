<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\SoftDeletes;

class Payment extends Model
{
    use HasFactory, SoftDeletes;

    protected $fillable = [
        'booking_id',
        'amount',
        'method',
        'status',
        'transaction_ref',
        'meta',
    ];

    protected $casts = [
        'meta' => 'array',
        'amount' => 'decimal:2',
    ];

    /* ---------------- Relationships ---------------- */

    public function booking()
    {
        return $this->belongsTo(Booking::class);
    }

    /* ---------------- Scopes ---------------- */

    public function scopeSuccessful($query)
    {
        return $query->where('status', 'successful');
    }
}
