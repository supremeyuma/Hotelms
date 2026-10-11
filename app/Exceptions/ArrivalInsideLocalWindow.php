<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;

/**
 * Raised when an online attempt targets an arrival the front desk owns.
 */
class ArrivalInsideLocalWindow extends \DomainException
{
    public function __construct(public readonly CarbonInterface $checkIn, public readonly int $cutoffHours)
    {
        parent::__construct("Arrivals within {$cutoffHours} hours are handled by the front desk.");
    }
}
