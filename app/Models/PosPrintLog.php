<?php

namespace App\Models;

use App\Sync\Syncable;
use Illuminate\Database\Eloquent\Concerns\HasUlids;
use Illuminate\Database\Eloquent\Model;

class PosPrintLog extends Model
{
    use HasUlids, Syncable;

    protected $table = 'pos_print_logs';

    protected $fillable = [
        'docket_id',
        'printed_by',
        'type',
        'printer_name',
        'content',
        'printed_at',
        'success',
        'is_reprint',
        'reason',
        'node_id',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'success' => 'boolean',
        'is_reprint' => 'boolean',
    ];

    public function docket()
    {
        return $this->belongsTo(PosDocket::class, 'docket_id');
    }

    public function printedBy()
    {
        return $this->belongsTo(User::class, 'printed_by');
    }
}
