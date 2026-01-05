<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\RoomAccessToken;
use Inertia\Inertia;

class OrderHistoryController extends Controller
{
    public function index(string $token)
    {
        $access = RoomAccessToken::with('room')
            ->where('token', $token)
            ->firstOrFail();

        $orders = Order::with('items')
            ->where('booking_id', $access->booking_id)
            ->where('room_id', $access->room_id)
            ->latest()
            ->get();

        return Inertia::render('Guest/OrderHistory', [
            'orders' => $orders,
        ]);
    }
}
