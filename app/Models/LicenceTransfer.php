<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One self-service move of a licence between machines.
 *
 * Kept as an audit trail rather than only a counter: when a key turns up on its
 * third machine in a month, support needs to see where it has been.
 */
class LicenceTransfer extends Model
{
    protected $fillable = [
        'licence_id',
        'from_machine_id',
        'to_machine_id',
        'ip_address',
        'user_agent',
    ];

    public function licence(): BelongsTo
    {
        return $this->belongsTo(Licence::class);
    }
}
