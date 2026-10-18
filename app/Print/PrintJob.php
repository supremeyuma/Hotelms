<?php

namespace App\Print;

use App\Models\PosDocket;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One queued thermal receipt. The application never writes to a printer; it
 * writes a job row and hands it to the local print bridge, which owns every
 * byte sent to the device. A single `status` column is an accurate model here
 * because a job has exactly one destination.
 */
class PrintJob extends Model
{
    public const STATUS_QUEUED = 'queued';

    public const STATUS_SENT = 'sent';

    public const STATUS_DONE = 'done';

    public const STATUS_FAILED = 'failed';

    protected $fillable = [
        'docket_id',
        'node_id',
        'is_reprint',
        'status',
        'attempts',
        'error',
        'printed_at',
    ];

    protected $casts = [
        'printed_at' => 'datetime',
        'attempts' => 'integer',
        'is_reprint' => 'boolean',
    ];

    public function docket(): BelongsTo
    {
        return $this->belongsTo(PosDocket::class, 'docket_id');
    }

    public static function createForDocket(PosDocket $docket, bool $isReprint = false, ?string $nodeId = null): self
    {
        return self::create([
            'docket_id' => $docket->id,
            'node_id' => $nodeId ?? (string) config('sync.node_id'),
            'is_reprint' => $isReprint,
            'status' => self::STATUS_QUEUED,
            'attempts' => 0,
        ]);
    }
}
