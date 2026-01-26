<?php

namespace App\Exceptions;

use Exception;

class InsufficientInventoryException extends Exception
{
    public int $available;

    public function __construct(int $available)
    {
        parent::__construct('Insufficient inventory available.');
        $this->available = $available;
    }
}
