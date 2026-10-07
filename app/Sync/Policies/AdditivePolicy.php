<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

class AdditivePolicy extends SyncPolicy
{
    /**
     * Class 3: derived balances.
     *
     * A Class 3 value is a cache of a sum over Class 1 movements and is never
     * exchanged as authoritative. The incoming balance is dropped rather than
     * merged, because carrying a remote balance forward would import
     * projection drift. The applier recomputes the sum from the ledger at
     * apply time instead.
     */
    public function merge(array $local, array $remote): array|ConflictResult
    {
        $merged = $local;

        unset($merged['quantity'], $merged['total_stock']);

        return $merged;
    }
}
