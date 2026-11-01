<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A licence purchase, from checkout until the key is issued.
 *
 * `status`: pending → paid, or pending → failed/cancelled. The order is the
 * idempotency boundary for the gateway callback and webhook: completing it twice
 * issues one licence, not two.
 */
class LicenceOrder extends Model
{
    public const STATUS_PENDING = 'pending';
    public const STATUS_PAID = 'paid';
    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'reference',
        'licence_plan_id',
        'property_name',
        'rooms',
        'customer_name',
        'customer_email',
        'customer_phone',
        'currency',
        'monthly_amount',
        'term_months',
        'amount',
        'provider',
        'status',
        'provider_reference',
        'paid_at',
        'licence_id',
        'key_ciphertext',
        'meta',
    ];

    protected $casts = [
        'rooms' => 'integer',
        'monthly_amount' => 'decimal:2',
        'term_months' => 'integer',
        'amount' => 'decimal:2',
        'paid_at' => 'datetime',
        'key_ciphertext' => 'encrypted',
        'meta' => 'array',
    ];

    public function plan(): BelongsTo
    {
        return $this->belongsTo(LicencePlan::class, 'licence_plan_id');
    }

    public function licence(): BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }

    public function isPaid(): bool
    {
        return $this->status === self::STATUS_PAID && $this->licence_id !== null;
    }
}
