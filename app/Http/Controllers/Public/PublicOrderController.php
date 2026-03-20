<?php

namespace App\Http\Controllers\Public;

use App\Http\Controllers\Controller;
use App\Models\Order;
use App\Models\OrderItem;
use App\Enums\OrderStatus;
use App\Services\AuditLoggerService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;

class PublicOrderController extends Controller
{
    /**
     * Store a new online order from public customers
     * Prepaid payment only
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'department'      => 'required|in:kitchen,bar',
            'items'           => 'required|array|min:1',
            'items.*.name'    => 'required|string|max:255',
            'items.*.price'   => 'required|numeric|min:0',
            'items.*.quantity'=> 'required|integer|min:1',
            'items.*.note'    => 'nullable|string|max:255',
        ]);

        try {
            $order = DB::transaction(function () use ($validated) {
                // Create order for public/anonymous user
                $order = Order::create([
                    'department'      => $validated['department'],
                    'status'          => OrderStatus::PENDING,
                    'service_area'    => $validated['department'],
                    'payment_method'  => 'online', // Prepaid only for public orders
                    'payment_status'  => 'pending',
                    'order_code'      => strtoupper('PUB-' . uniqid()),
                    'total'           => 0,
                    'user_id'         => Auth::check() ? Auth::id() : null,
                ]);

                $total = 0;

                // Add order items
                foreach ($validated['items'] as $item) {
                    OrderItem::create([
                        'order_id'   => $order->id,
                        'item_name'  => $item['name'],
                        'price'      => $item['price'],
                        'qty'        => $item['quantity'],
                        'note'       => $item['note'] ?? null,
                    ]);

                    $total += $item['price'] * $item['quantity'];
                }

                $order->update(['total' => $total]);

                // TODO: Implement audit logging once AuditLogger is properly set up
                // AuditLoggerService::log('public_order_created', $order);

                return $order;
            });

            // Redirect to payment initialization
            return inertia()->location(
                route('payments.initialize.public.order', ['order' => $order->id])
            );

        } catch (\Throwable $e) {
            report($e);

            return back()->with(
                'error',
                'Failed to place order. Please try again.'
            );
        }
    }
}
