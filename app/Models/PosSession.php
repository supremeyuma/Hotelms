<?php

namespace App\Models;

use App\Sync\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class PosSession extends Model
{
    use HasFactory, HasUlids, Syncable;

    protected $fillable = [
        'device_id',
        'user_id',
        'opened_at',
        'closed_at',
        'cash_start',
        'cash_declared',
        'cash_verified',
        'cash_variance',
        'card_total',
        'total_sales',
        'docket_count',
        'status',
        'supervisor_id',
        'reconciled_at',
        'notes',
    ];

    protected $casts = [
        'opened_at' => 'datetime',
        'closed_at' => 'datetime',
        'reconciled_at' => 'datetime',
        'cash_start' => 'decimal:2',
        'cash_declared' => 'decimal:2',
        'cash_verified' => 'decimal:2',
        'cash_variance' => 'decimal:2',
        'card_total' => 'decimal:2',
        'total_sales' => 'decimal:2',
    ];

    public function device()
    {
        return $this->belongsTo(PosDevice::class, 'device_id');
    }

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function supervisor()
    {
        return $this->belongsTo(User::class, 'supervisor_id');
    }

    public function dockets()
    {
        return $this->hasMany(PosDocket::class, 'session_id');
    }
}
