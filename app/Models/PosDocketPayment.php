<?php

namespace App\Models;

use App\Sync\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PosDocketPayment extends Model
{
    use HasUlids, Syncable;

    protected $fillable = [
        'docket_id',
        'payment_method',
        'amount',
        'reference',
        'change_given',
        'meta',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
        'change_given' => 'decimal:2',
        'meta' => 'array',
    ];

    public function docket()
    {
        return $this->belongsTo(PosDocket::class, 'docket_id');
    }
}
