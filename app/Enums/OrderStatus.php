<?php

namespace App\Enums;

enum OrderStatus: string
{
    case PENDING = 'pending';
    case PROCESSING = 'processing';
    case READY = 'ready';
    case DELIVERED = 'delivered';
    case COMPLETED = 'completed';
    case CANCELLED = 'cancelled';

    public static function active(): array
    {
        return [
            self::PENDING->value,
            self::PROCESSING->value,
            self::READY->value,
        ];
    }
}
