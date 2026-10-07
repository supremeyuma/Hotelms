<?php

namespace App\Models\Sync;

use App\Sync\Syncable;
use Illuminate\Database\Eloquent\Model;

class SyncChange extends Model
{
    use Syncable;

    protected $table = "sync_changes";

    protected $guarded = [];

    protected $casts = [
        'payload'    => 'array',
        'sequence'   => 'integer',
        'applied_at' => 'datetime',
    ];
}
