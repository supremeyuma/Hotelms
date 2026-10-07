<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

class SyncLogger
{
    /**
     * True while a remote change is being applied. Suppresses capture so a
     * synchronised write does not append a second change row, which would
     * echo back to the peer forever.
     */
    private static bool $applying = false;

    public static function applyingRemote(callable $callback): mixed
    {
        $previous = self::$applying;

        self::$applying = true;

        try {
            return $callback();
        } finally {
            self::$applying = $previous;
        }
    }

    public static function isApplying(): bool
    {
        return self::$applying;
    }

    /**
     * Append one change row for a write to a syncable model.
     *
     * Returns null when appending a change would be wrong: sync is disabled,
     * a remote change is being applied, the entity has no registered policy,
     * the table is read-only for this node kind, or this node has no identity
     * to attribute the change to.
     */
    public static function capture(Model $row, string $operation): ?SyncChange
    {
        if (! config('sync.enabled') || self::$applying) {
            return null;
        }

        $nodeId = (string) config('sync.node_id');

        if ($nodeId === '') {
            return null;
        }

        $entityKey = $row->getSyncTable();

        if (! SyncScope::has($entityKey)) {
            return null;
        }

        if (! SyncScope::mayEmit($entityKey)) {
            return null;
        }

        return SyncChange::create([
            'table_name' => $entityKey,
            'row_id'     => $row->getSyncId(),
            'operation'  => $operation,
            'node_id'    => $nodeId,
            'hlc'        => (string) $row->sync_hlc,
            'sequence'   => self::nextSequence($nodeId),
            'payload'    => $row->attributesToArray(),
        ]);
    }

    /**
     * Per-node monotonic sequence, per spec section 5.4.
     *
     * Allocated under a row lock on a per-node counter row, NOT by reading
     * max(sequence) + 1. max+1 is a race: two concurrent writes on one node
     * both read the same max, both mint the same sequence, and the
     * (node_id, sequence) unique index turns a lost write into a failed sale.
     *
     * insertOrIgnore rather than insert: on the very first capture for a node
     * two concurrent transactions can both find no counter row, and a plain
     * insert would fail one of them on the primary key. The row is created
     * once, then both the read and the update below run under lockForUpdate.
     */
    public static function nextSequence(?string $nodeId = null): int
    {
        $nodeId ??= (string) config('sync.node_id');

        return DB::transaction(function () use ($nodeId): int {
            DB::table('sync_sequences')->insertOrIgnore([
                'node_id'  => $nodeId,
                'sequence' => 0,
            ]);

            $counter = DB::table('sync_sequences')
                ->where('node_id', $nodeId)
                ->lockForUpdate()
                ->first();

            $next = ((int) $counter->sequence) + 1;

            DB::table('sync_sequences')
                ->where('node_id', $nodeId)
                ->update(['sequence' => $next]);

            return $next;
        });
    }
}
