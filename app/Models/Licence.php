<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Licence extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'key_hash',
        'key_prefix',
        'property_name',
        'customer_name',
        'customer_email',
        'plan',
        'term_months',
        'status',
        'machine_id',
        'issued_at',
        'activated_at',
        'expires_at',
        'payment_reference',
        'notes',
    ];

    protected $casts = [
        'issued_at' => 'datetime',
        'activated_at' => 'datetime',
        'expires_at' => 'datetime',
        'term_months' => 'integer',
    ];

    /**
     * A licence is usable while it is active or issued (not yet activated),
     * has not been revoked, and has not expired.
     */
    public function isUsable(): bool
    {
        if ($this->status === self::STATUS_REVOKED) {
            return false;
        }

        return $this->expires_at === null || $this->expires_at->isFuture();
    }
}
