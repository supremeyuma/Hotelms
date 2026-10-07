<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;
use App\Sync\Hlc;

class MergePolicy extends SyncPolicy
{
    /**
     * Class 1: immutable facts.
     *
     * Two nodes creating rows of the same class both survive, so no conflict is
     * possible. Same row key means the same fact seen twice, so the newer HLC
     * wins. Different row keys mean two genuinely distinct facts -- two dockets,
     * two payments -- and both must survive, because returning one of them here
     * would silently discard a sale.
     */
    public function merge(array $local, array $remote): array|ConflictResult
    {
        if (($local['sync_row_key'] ?? null) !== ($remote['sync_row_key'] ?? null)) {
            return [$local, $remote];
        }

        return $this->newest($local, $remote);
    }

    private function newest(array $local, array $remote): array
    {
        $localHlc = $this->hlcOf($local);
        $remoteHlc = $this->hlcOf($remote);

        if ($remoteHlc === null) {
            return $local;
        }

        if ($localHlc === null) {
            return $remote;
        }

        if ($localHlc->toString() !== $remoteHlc->toString()) {
            // Hlc::max applies the total order of timestamp, counter and node
            // id, so both nodes reach the same answer without coordinating.
            return Hlc::max($localHlc, $remoteHlc)->toString() === $remoteHlc->toString()
                ? $remote
                : $local;
        }

        // Identical HLC: the clock, counter and node matched, so the two rows
        // describe the same write. Break the tie on the row's origin node so
        // every node that compares them still lands on the same choice.
        return (string) ($remote['sync_origin_node_id'] ?? '')
            > (string) ($local['sync_origin_node_id'] ?? '')
            ? $remote
            : $local;
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
            // A malformed HLC is not a crash and not a win. Treat the row as
            // never-synced so it loses to any row carrying a real timestamp.
            return null;
        }
    }
}
