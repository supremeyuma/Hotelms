<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;
use App\Sync\Hlc;

/**
 * Class 2: row-level last-write-wins.
 *
 * The newest sync_hlc wins and sync_origin_node_id breaks an exact tie. Never
 * surfaced to a human. This is row-level rather than the field-level the spec
 * originally described, because one sync_hlc per row cannot express per-field
 * write times. See plan Task 3 Step 4.
 */
class LastWriteWinsPolicy extends SyncPolicy
{
    private const META = ['sync_hlc', 'sync_origin_node_id', 'sync_deleted_at', 'sync_row_key'];

    public function merge(array $local, array $remote): array|ConflictResult
    {
        $localHlc = $this->hlcOf($local);
        $remoteHlc = $this->hlcOf($remote);

        // A row that has never synced has no HLC and loses to anything that has.
        $remoteWins = $remoteHlc !== null
            && ($localHlc === null || $this->newer($remoteHlc, $localHlc, $remote, $local));

        $winner = $remoteWins ? $remote : $local;
        $loser = $remoteWins ? $local : $remote;

        // The loser contributes only fields the winner does not have, so a
        // partially-populated remote row cannot erase local data by omission.
        $merged = $winner;

        foreach ($loser as $field => $value) {
            if (in_array($field, self::META, true)) {
                continue;
            }

            if (! array_key_exists($field, $merged)) {
                $merged[$field] = $value;
            }
        }

        $merged['sync_hlc'] = $this->highest($localHlc, $remoteHlc);
        $merged['sync_origin_node_id'] = $remoteWins
            ? ($remote['sync_origin_node_id'] ?? null)
            : ($local['sync_origin_node_id'] ?? null);

        return $merged;
    }

    private function newer(Hlc $candidate, Hlc $incumbent, array $candidateRow, array $incumbentRow): bool
    {
        if ($candidate->toString() === $incumbent->toString()) {
            // Identical HLC: fall back to node id for a deterministic answer
            // that is the same on every node comparing these two rows.
            return (string) ($candidateRow['sync_origin_node_id'] ?? '')
                > (string) ($incumbentRow['sync_origin_node_id'] ?? '');
        }

        return Hlc::max($candidate, $incumbent)->toString() === $candidate->toString();
    }

    /**
     * Highest of the two stamps, tolerating a row that carries none.
     *
     * Hlc::max() declares non-nullable parameters, so passing a null through
     * would raise a TypeError instead of producing a merged stamp. A row
     * created by a migration rather than an application write has no HLC; it
     * must not crash the merge.
     */
    private function highest(?Hlc $local, ?Hlc $remote): ?string
    {
        if ($local === null) {
            return $remote?->toString();
        }

        if ($remote === null) {
            return $local->toString();
        }

        return Hlc::max($local, $remote)->toString();
    }

    private function hlcOf(array $row): ?Hlc
    {
        $raw = trim((string) ($row['sync_hlc'] ?? ''));

        if ($raw === '') {
            return null;
        }

        try {
            return Hlc::fromString($raw);
        } catch (\InvalidArgumentException) {
            // An unparseable HLC is not a crash and not a win. Treat the row
            // as never-synced so it loses to any row that has a real timestamp.
            return null;
        }
    }
}
