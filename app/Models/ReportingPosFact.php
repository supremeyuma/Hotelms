<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class ReportingPosFact extends Model
{
    use HasUlids;

    protected $table = 'reporting_pos_facts';

    protected $fillable = [
        'fact_date',
        'device_id',
        'session_id',
        'device_type',
        'total_sales',
        'transaction_count',
        'item_count',
        'cash_sales',
        'card_sales',
        'room_charge_sales',
        'discounts_total',
        'voids_count',
        'voids_total',
        'top_items',
        'hourly_breakdown',
    ];

    protected $casts = [
        'fact_date' => 'date',
        'total_sales' => 'decimal:2',
        'transaction_count' => 'integer',
        'item_count' => 'integer',
        'cash_sales' => 'decimal:2',
        'card_sales' => 'decimal:2',
        'room_charge_sales' => 'decimal:2',
        'discounts_total' => 'decimal:2',
        'voids_count' => 'integer',
        'voids_total' => 'decimal:2',
        'top_items' => 'array',
        'hourly_breakdown' => 'array',
    ];

    public function device()
    {
        return $this->belongsTo(PosDevice::class);
    }

    public function session()
    {
        return $this->belongsTo(PosSession::class);
    }
}
