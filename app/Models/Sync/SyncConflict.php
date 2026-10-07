<?php

namespace App\Models\Sync;

use Illuminate\Database\Eloquent\Model;

class SyncConflict extends Model
{
    protected $table = 'sync_conflicts';

    protected $guarded = [];

    protected $casts = [
        'local_change' => 'array',
        'remote_change' => 'array',
        'resolution' => 'array',
        'resolved_at' => 'datetime',
    ];
}
