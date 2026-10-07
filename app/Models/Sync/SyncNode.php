<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;

class SyncNode extends Model
{
    protected $table = 'sync_nodes';

    protected $guarded = [];

    protected $casts = [
        'meta' => 'array',
        'last_seen_at' => 'datetime',
    ];
}
