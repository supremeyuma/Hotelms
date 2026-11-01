<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

class Licence extends Model
{
    public const STATUS_ISSUED = 'issued';
    public const STATUS_ACTIVE = 'active';
    public const STATUS_REVOKED = 'revoked';

    protected $fillable = [
        'licence_plan_id',
        'key_hash',
        'key_prefix',
        'property_name',
        'room_count',
        'customer_name',
        'customer_email',
        'plan',
        'term_months',
        'currency',
        'monthly_amount',
        'amount',
        'status',
        'machine_id',
        'transfer_count',
        'previous_machine_id',
        'last_transferred_at',
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
        'last_transferred_at' => 'datetime',
        'term_months' => 'integer',
        'room_count' => 'integer',
        'transfer_count' => 'integer',
        'monthly_amount' => 'decimal:2',
        'amount' => 'decimal:2',
    ];

    public function transfers(): HasMany
    {
        return $this->hasMany(LicenceTransfer::class)->latest('id');
    }

    public function transferLimit(): int
    {
        return max(0, (int) config('licensing.self_service_transfers', 2));
    }

    public function transfersRemaining(): int
    {
        return max(0, $this->transferLimit() - $this->transfer_count);
    }

    /**
     * The price tier this licence was sold at.
     *
     * Named `licencePlan` rather than `plan` because `plan` is also the billing
     * period column ('annual'); an attribute always wins over a same-named
     * relation, which would silently return the string.
     */
    public function licencePlan(): BelongsTo
    {
        return $this->belongsTo(LicencePlan::class, 'licence_plan_id');
    }

    public function order(): HasOne
    {
        return $this->hasOne(LicenceOrder::class);
    }

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

    /**
     * Human status for the admin list: issued keys that were never activated are
     * different from ones running out, and both are different from expired.
     */
    public function displayStatus(): string
    {
        if ($this->status === self::STATUS_REVOKED) {
            return 'revoked';
        }

        if ($this->expires_at !== null && $this->expires_at->isPast()) {
            return 'expired';
        }

        if ($this->status === self::STATUS_ISSUED) {
            return 'not activated';
        }

        return 'active';
    }

    public function daysUntilExpiry(): ?int
    {
        return $this->expires_at === null
            ? null
            : (int) now()->startOfDay()->diffInDays($this->expires_at->copy()->startOfDay(), false);
    }
}
