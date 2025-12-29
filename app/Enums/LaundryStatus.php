<?php

namespace App\Enums;

enum LaundryStatus: string
{
    case REQUESTED = 'requested';
    case PICKUP_SCHEDULED = 'pickup_scheduled';
    case PICKED_UP = 'picked_up';
    case WASHING = 'washing';
    case READY = 'ready';
    case DELIVERED = 'delivered';
    case CANCELLED = 'cancelled';

    public static function allowedTransitions(self $from): array
    {
        return match ($from) {
            self::REQUESTED => [self::PICKUP_SCHEDULED, self::CANCELLED],
            self::PICKUP_SCHEDULED => [self::PICKED_UP, self::CANCELLED],
            self::PICKED_UP => [self::WASHING],
            self::WASHING => [self::READY],
            self::READY => [self::DELIVERED],
            default => [],
        };
    }
}
