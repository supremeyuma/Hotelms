<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class DiscountCodeRedemption extends Model
{
    use HasFactory;

    protected $fillable = [
        'discount_code_id',
        'scope',
        'status',
        'rooms_used',
        'base_amount',
        'discount_amount',
        'meta',
        'released_at',
    ];

    protected $casts = [
        'base_amount' => 'decimal:2',
        'discount_amount' => 'decimal:2',
        'meta' => 'array',
        'released_at' => 'datetime',
    ];

    public function discountCode(): BelongsTo
    {
        return $this->belongsTo(DiscountCode::class);
    }

    public function redeemable(): MorphTo
    {
        return $this->morphTo();
    }
}
