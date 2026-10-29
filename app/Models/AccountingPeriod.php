<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class AccountingPeriod extends Model
{
    protected $fillable = [
        'start_date',
        'end_date',
        'is_closed',
    ];

    /**
     * Dates must be Carbon instances: PeriodService calls `->addDay()` on the
     * last period's end date, and without a cast that is a string — the
     * accounting-periods index answered 500 as soon as a period existed.
     */
    protected $casts = [
        'start_date' => 'date',
        'end_date' => 'date',
        'is_closed' => 'boolean',
    ];
}
