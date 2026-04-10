<?php

namespace App\Http\Controllers\Guest;

use App\Http\Controllers\Controller;
use App\Models\Order;
use Illuminate\Http\Request;
use Inertia\Inertia;

class OrderHistoryController extends Controller
{
    public function index(Request $request)
    {
        $access = $request->attributes->get('roomAccessToken');

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
