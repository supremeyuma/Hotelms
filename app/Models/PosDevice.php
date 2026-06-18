<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PosDevice extends Model
{
    use HasUlids, SoftDeletes;

    protected $fillable = [
        'device_code',
        'device_name',
        'device_type',
        'api_token',
        'last_ping_at',
        'is_active',
        'metadata',
    ];

    protected $casts = [
        'is_active' => 'boolean',
        'last_ping_at' => 'datetime',
        'metadata' => 'array',
    ];

    public function sessions()
    {
        return $this->hasMany(PosSession::class);
    }

    public function dockets()
    {
        return $this->hasMany(PosDocket::class);
    }
}
