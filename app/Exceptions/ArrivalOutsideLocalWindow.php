<?php

namespace App\Exceptions;

use Carbon\CarbonInterface;

/**
 * Raised when a front-desk attempt targets an arrival the cloud owns.
 */
class ArrivalOutsideLocalWindow extends \DomainException
{
    public function __construct(public readonly CarbonInterface $checkIn, public readonly int $cutoffHours)
    {
        parent::__construct("Arrivals beyond {$cutoffHours} hours are taken on the cloud.");
    }
}
