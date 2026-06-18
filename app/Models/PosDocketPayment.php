<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PosDocketPayment extends Model
{
    use HasUlids;

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
        return $this->belongsTo(PosDocket::class);
    }
}
