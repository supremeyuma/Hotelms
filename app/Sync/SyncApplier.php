<?php

namespace App\Sync;

use App\Models\Sync\SyncChange;
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

                $instance ??= new $model;

                $instance->forceFill($this->attributesFor($instance, $payload, $uid))->save();
            });
        });
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
