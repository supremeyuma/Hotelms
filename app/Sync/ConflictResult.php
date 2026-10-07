<?php

namespace App\Sync;

final class ConflictResult
{
    public function __construct(
        public readonly array $local,
        public readonly ?array $remote,
        public readonly string $reason = '',
    ) {
    }

    public function isBlocked(): bool
    {
        return true;
    }
}
