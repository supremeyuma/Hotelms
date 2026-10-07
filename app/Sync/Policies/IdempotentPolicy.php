<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class IdempotentPolicy extends SyncPolicy
{
    /**
     * Class 5 and replay: applying a change whose row key already exists
     * locally is a no-op, so a re-delivered batch cannot create a second copy
     * of a row. The pull path relies on this rather than re-deriving the check.
     */
    public function merge(array $local, array $remote): array|ConflictResult
    {
        if (($local['sync_row_key'] ?? null) === ($remote['sync_row_key'] ?? null)) {
            return $local;
        }

        return $remote;
    }
}
