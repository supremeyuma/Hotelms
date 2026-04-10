<?php

namespace App\Exceptions;

use Exception;

class InsufficientInventoryException extends Exception
{
    public float $available;

    public function __construct(float $available)
    {
        parent::__construct('Insufficient inventory available.');
        $this->available = $available;
    }
}
