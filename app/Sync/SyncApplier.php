<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
use App\Models\Sync\SyncConflict;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Arr;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SyncApplier
{
    /**
     * Table columns per model, resolved once per process.
     *
     * Schema introspection on every apply would cost one query per change,
     * which for a 500-row batch is 500 queries spent discovering something
     * that does not change while the process runs.
     *
     * @var array<string, list<string>>
     */
    private static array $columns = [];

    /**
     * Apply one remote change locally.
     *
     * Wrapped in a transaction and run inside applyingRemote() so the write
     * is not re-logged as a local change.
     *
     * @throws \RuntimeException when the entity key has no registered model
     */
    public function apply(SyncChange $change): void
    {
        $payload = is_array($change->payload) ? $change->payload : [];
        $model = SyncEntityRegistry::modelFor($change->table_name);

        DB::transaction(function () use ($model, $change, $payload): void {
            SyncLogger::applyingRemote(function () use ($model, $change, $payload): void {
                // Identity is sync_uid, never the integer primary key.
                //
                // Part A widened sync_changes.row_id to a string and added a
                // sync_uid column to every synced domain table. Row id from the
                // origin node is the ULID; `id` is this node's own auto-increment
                // value and means something different on each node. Looking up
                // by `id` would either find the wrong row or, once the ids
                // diverge, create a duplicate.
                $uid = (string) ($payload['sync_uid'] ?? $change->row_id);

                $instance = $model::query()
                    ->where('sync_uid', $uid)
                    ->lockForUpdate()
                    ->first();

                if ($change->operation === 'delete') {
                    $instance?->delete();

                    return;
                }

                if ($instance === null) {
                    // A row this node has never seen: adopt it as it stands.
                    // Two nodes that created distinct rows must both survive,
                    // and there is nothing of ours to merge it against.
                    (new $model)
                        ->forceFill($this->attributesFor(new $model, $payload, $uid))
                        ->save();

                    return;
                }

                // The row exists on both sides, and this node may have edited
                // it while they were disconnected. Applying the remote payload
                // over it wholesale would silently discard a newer local write,
                // which is the exact divergence this section exists to prevent.
                // The change goes through the catalogue policy, and the policy
                // answer is what gets persisted - or, when blocked, a conflict
                // instead of either side.
                $this->mergeAndSave($instance, $payload, $uid, $change->table_name);
            });
        });
    }

    /**
     * Merge this node's row with the remote payload and persist the result.
     */
    private function mergeAndSave(Model $instance, array $payload, string $uid, string $table): void
    {
        // attributesToArray() rather than getAttributes(): the payload was
        // built by attributesToArray() on the origin, so the two rows must be
        // in the same shape before a policy compares them. Raw database
        // attributes hold timezone-less timestamps while the payload holds
        // ISO-8601, and comparing a date across those two encodings is a
        // silent wrong answer rather than an error.
        $merged = SyncMerger::merge($table, $instance->attributesToArray(), $payload);

        if ($merged instanceof ConflictResult) {
            // The policy refused to pick a winner: two rows claim the same
            // scarce resource. Neither side is written until a human decides.
            $this->recordConflict($table, $uid, $merged);

            return;
        }

        if (array_is_list($merged)) {
            // Both rows are meant to survive but they arrived under one
            // sync_uid, and sync_uid is unique - writing the second would
            // fail the index, and dropping either would lose work. Nothing in
            // the current catalogue can reach this branch: it is guarded by
            // sync_row_key, a column no migration defines, so the check is
            // always `false`. If a future table adds the column, a wrong merge
            // becomes a recorded conflict rather than a lost row.
            $this->recordConflict($table, $uid, new ConflictResult(
                $instance->attributesToArray(),
                $payload,
                'distinct_rows_share_one_identity',
            ));

            return;
        }

        $instance->forceFill($this->attributesFor($instance, $merged, $uid))->save();
    }

    /**
     * Record an unresolved merge for human decision.
     *
     * Applying the same change on a replay must not pile up rows: the log is
     * re-applied over the whole life of a node, so a conflict is keyed by its
     * incoming payload as well as the row it concerns. A genuinely different
     * remote payload produces its own row.
     */
    private function recordConflict(string $table, string $uid, ConflictResult $result): void
    {
        $remote = $result->remote;

        $alreadyOpen = SyncConflict::query()
            ->where('table_name', $table)
            ->where('row_id', $uid)
            ->where('status', 'open')
            ->when(
                $remote === null,
                fn ($query) => $query->whereNull('remote_change'),
                fn ($query) => $query->where('remote_change', json_encode($remote, JSON_UNESCAPED_UNICODE)),
            )
            ->exists();

        if ($alreadyOpen) {
            return;
        }

        SyncConflict::create([
            'table_name'    => $table,
            'row_id'        => $uid,
            'local_change'  => $result->local,
            'remote_change' => $remote,
            'reason'        => $result->reason,
            'status'        => 'open',
        ]);
    }

    /**
     * Apply a batch, advancing a cursor only after the whole batch commits.
     *
     * @param  iterable<SyncChange>  $changes
     */
    public function applyBatch(iterable $changes, int $throughChangeId): void
    {
        foreach ($changes as $change) {
            $this->apply($change);
        }

        // Cursor advance belongs to Phase 4R, which owns sync_cursors.
    }

    /**
     * Narrow a remote payload to the columns this table actually has.
     *
     * @return array<string, mixed>
     */
    private function attributesFor(Model $instance, array $payload, string $uid): array
    {
        $table = $instance->getTable();

        $columns = self::$columns[$table] ??= Schema::getColumnListing($table);

        $attributes = Arr::only($payload, $columns);

        // The origin's primary key is meaningless here. Keeping it would
        // collide with an existing row on insert and, on update, would
        // silently repoint this node's own identifiers.
        unset($attributes['id']);

        $attributes['sync_uid'] = $uid;

        return $this->localiseDates($instance, $attributes);
    }

    /**
     * Render captured timestamps in this node's own database format.
     *
     * The payload serialises dates as UTC, which is the right thing to send:
     * it is the instant itself, and it stays correct when the two nodes sit
     * in different timezones. But a date column holds a timezone-less string
     * that this engine reads back in `app.timezone`, and assigning an
     * ISO-8601 value makes Eloquent format the instant in UTC instead - so a
     * replay would shift every timestamp by the UTC offset, and the same log
     * applied twice would not be byte-identical.
     *
     * @param  array<string, mixed>  $attributes
     * @return array<string, mixed>
     */
    private function localiseDates(Model $instance, array $attributes): array
    {
        foreach ($instance->getDates() as $dateAttribute) {
            $value = $attributes[$dateAttribute] ?? null;

            if (! is_string($value) || $value === '') {
                continue;
            }

            $attributes[$dateAttribute] = Carbon::parse($value)
                ->setTimezone(config('app.timezone'))
                ->format($instance->getDateFormat());
        }

        return $attributes;
    }
}
