<?php

namespace App\Sync;

class SyncMerger
{
    public static function merge(array $local, array $remote, array $policy = []): array
    {
        return array_merge($local, $remote);
    }
}
