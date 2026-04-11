<?php

namespace App\Services;

use App\Models\Order;

class OrderChargeService
{
    public function __construct(
        protected StaffRoomOrderService $staffRoomOrderService,
    ) {}

    public function post(Order $order): void
    {
        $this->staffRoomOrderService->ensureCharge($order);
    }
}
