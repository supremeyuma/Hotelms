<?php

namespace App\Sync\Policies;

use App\Sync\ConflictResult;

abstract class SyncPolicy
{
    /**
     * Merge a local row with a remote row for the same entity.
     *
     * Pure by contract: identical inputs must produce identical output on
     * every node. No database access, no clock reads, no randomness.
     *
     * A Class 1 union may return a list of two rows rather than one row. The
     * dispatcher must write every element of a returned list, not pick one.
     *
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>|list<array<string, mixed>>|ConflictResult
     */
    abstract public function merge(array $local, array $remote): array|ConflictResult;
}
