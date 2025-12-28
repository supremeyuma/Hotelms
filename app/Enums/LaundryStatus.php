<?php

// app/Enums/LaundryStatus.php

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
}
