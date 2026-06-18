<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Concerns\HasUlids;

class PosPrintLog extends Model
{
    use HasUlids;

    protected $table = 'pos_print_logs';

    protected $fillable = [
        'docket_id',
        'type',
        'printer_name',
        'content',
        'printed_at',
        'success',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'success' => 'boolean',
    ];

    public function docket()
    {
        return $this->belongsTo(PosDocket::class);
    }
}
