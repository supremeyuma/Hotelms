<?php

namespace App\Sync;

trait Syncable
{
    public function getSyncId(): string
    {
        return (string) $this->id;
    }

    public function getSyncTable(): string
    {
        return $this->getTable();
    }
}
