<?php

namespace App\Sync;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

trait Syncable
{
    /**
     * Register capture on write.
     *
     * `saved` rather than `creating`: the change row references the row, so
     * the row must exist first, and the HLC must be persisted onto the row
     * itself rather than held in memory.
     */
    public static function bootSyncable(): void
    {
        static::creating(function (Model $row): void {
            $row->mintSyncUidIfMissing();
        });

        static::saved(function (Model $row): void {
            $row->stampSyncMetadata();
            SyncLogger::capture($row, $row->wasRecentlyCreated ? 'create' : 'update');
        });

        static::deleted(function (Model $row): void {
            SyncLogger::capture($row, 'delete');
        });
    }

    /**
     * Mint the ULID this row is addressed by, before it is inserted.
     *
     * Done on `creating` rather than only when sync is enabled so a row
     * written while sync is off is still addressable the moment it is turned
     * on. The column already exists on every synced table, so this costs one
     * extra field in the INSERT and no extra query.
     *
     * Scoped to catalogue tables because `SyncChange` uses this trait and has
     * no `sync_uid` column: stamping it would put a non-existent column in the
     * statement and fail the insert outright.
     */
    public function mintSyncUidIfMissing(): void
    {
        if (! SyncScope::has($this->getSyncTable())) {
            return;
        }

        if (empty($this->sync_uid)) {
            $this->sync_uid = (string) Str::ulid();
        }
    }

    /**
     * Write a fresh HLC and this node's id onto the row.
     *
     * Persisted with saveQuietly so the HLC write does not itself re-enter
     * the `saved` hook and log a second change.
     *
     * Both the sequence and the HLC are refused when the node has no
     * identity. An HLC built from an empty node id is meaningless, and a
     * change row logged under an empty node id would interleave with every
     * other unconfigured node's sequence.
     */
    public function stampSyncMetadata(): void
    {
        if (! config('sync.enabled')) {
            return;
        }

        if (! SyncScope::has($this->getSyncTable())) {
            return;
        }

        if ((string) config('sync.node_id') === '') {
            return;
        }

        $this->mintSyncUidIfMissing();

        $this->sync_hlc = Hlc::now((string) config('sync.node_id'))->toString();
        $this->sync_origin_node_id = (string) config('sync.node_id');

        if ($this->isDirty()) {
            $this->saveQuietly();
        }
    }

    /**
     * The ULID in sync_uid, never the integer primary key.
     *
     * Part A added a nullable, unique sync_uid column to every synced domain
     * table and backfilled it. `id` is local to this engine and diverges
     * across nodes, so it cannot identify a row for sync purposes.
     */
    public function getSyncId(): string
    {
        return (string) ($this->sync_uid ?: Str::ulid());
    }

    public function getSyncTable(): string
    {
        return $this->getTable();
    }
}
