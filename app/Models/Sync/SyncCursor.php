<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;

class SyncCursor extends Model
{
    protected $table = 'sync_cursors';

    protected $guarded = [];

    protected $casts = [
        'last_synced_at' => 'datetime',
    ];
}
