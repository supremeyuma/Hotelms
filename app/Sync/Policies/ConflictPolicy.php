<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class ConflictPolicy extends SyncPolicy
{
    /**
     * Class 4: block and surface.
     *
     * Two rows that claim the same scarce resource are not merged by rule, they
     * are handed to a human. Returning a ConflictResult stops the applier from
     * writing either row until someone resolves it.
     */
    public function merge(array $local, array $remote): array|ConflictResult
    {
        if ($this->overlaps($local, $remote)) {
            return new ConflictResult($local, $remote, 'overlapping_reservation');
        }

        return $local;
    }

    private function overlaps(array $local, array $remote): bool
    {
        if (($local['sync_row_key'] ?? null) === ($remote['sync_row_key'] ?? null)) {
            return false;
        }

        $localIn = (string) ($local['check_in'] ?? '');
        $localOut = (string) ($local['check_out'] ?? '');
        $remoteIn = (string) ($remote['check_in'] ?? '');
        $remoteOut = (string) ($remote['check_out'] ?? '');

        if ($localIn === '' || $localOut === '' || $remoteIn === '' || $remoteOut === '') {
            return false;
        }

        return $localIn < $remoteOut && $remoteIn < $localOut;
    }
}
