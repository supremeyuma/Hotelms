<?php

namespace App\Sync;

class SyncMerger
{
    /**
     * Pure merge. Identical inputs yield identical output on every node, which
     * is what makes it safe for a terminal and the cloud to merge the same two
     * changes independently and still converge.
     *
     * The policy comes from the catalogue, never from the caller. A
     * caller-supplied policy would let two nodes disagree about how to merge
     * the same two rows, and disagreement is exactly what a merge exists to
     * prevent.
     *
     * @param  array<string, mixed>  $local
     * @param  array<string, mixed>  $remote
     * @return array<string, mixed>|list<array<string, mixed>>|ConflictResult
     *
     * @throws \InvalidArgumentException when no policy is registered for the key
     */
    public static function merge(string $entityType, array $local, array $remote): array|ConflictResult
    {
        return SyncScope::policyFor($entityType)->merge($local, $remote);
    }
}
