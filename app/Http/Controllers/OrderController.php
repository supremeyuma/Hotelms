<?php

namespace App\Http\Controllers;

use App\Models\Order;
use App\Http\Requests\Order\StoreOrderRequest;
use App\Http\Requests\Order\UpdateOrderRequest;
use App\Services\OrderService;
use App\Services\AuditLogger;

class OrderController extends Controller
{
    public function __construct()
    {
        $this->authorizeResource(Order::class, 'order');
    }

    public function store(StoreOrderRequest $request, OrderService $service)
    {
        $order = $service->createOrder($request->validated());

        AuditLogger::log('order_created', 'Order', $order->id);

        return back()->with('success', 'Order created');
    }

    public function update(UpdateOrderRequest $request, Order $order, OrderService $service)
    {
        $service->updateOrder($order, $request->validated());

        AuditLogger::log('order_updated', 'Order', $order->id);

        return back()->with('success', 'Order updated');
    }

    public function destroy(Order $order, OrderService $service)
    {
        $service->cancelOrder($order);

        AuditLogger::log('order_cancelled', 'Order', $order->id);

        return back()->with('success', 'Order cancelled');
    }
}
