<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Http\Requests\OrderRequest;
use App\Http\Requests\OrderStatusRequest;
use App\Models\Order;
use App\Models\OrderItem;
use App\Models\Room;
use App\Enums\OrderStatus;
use App\Services\OrderService;
use App\Services\StaffActionService;
use App\Services\AuditLogger;
use App\Services\PaymentAccountingService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Http\Request;

class OrderController extends Controller
{
    /**
     * Store a new room-service order.
     */
    public function store(OrderRequest $request)
    {
        $validated = $request->validated();

        return DB::transaction(function () use ($validated, $request) {

            $paymentMethod = $validated['payment_method'] ?? 'postpaid';

            $order = Order::create([
                'room_id' => $validated['room_id'] ?? null,
                'user_id' => Auth::id(),
                'service_area' => $validated['service_area'],
                'order_code' => 'ORD-' . strtoupper(Str::random(10)),
                'status' => OrderStatus::PENDING,
                'notes' => $validated['notes'] ?? null,
                'total' => 0,
                'payment_method' => $paymentMethod,
                'payment_status' => $paymentMethod === 'online' ? 'pending' : 'not_required',
            ]);

            $total = 0;

            foreach ($validated['items'] as $item) {
                $line = OrderItem::create([
                    'order_id' => $order->id,
                    'item_name' => $item['item_name'],
                    'price' => $item['price'],
                    'qty' => $item['qty'],
                ]);

                $total += (float) $line->price * (int) $line->qty;
            }

            $order->update(['total' => $total]);

            // Staff action code (optional)
            if ($request->filled('action_code') && Auth::check()) {
                StaffActionService::validateAndRecord(
                    Auth::id(),
                    $request->input('action_code'),
                    'order_create',
                    $order
                );
            }

            // Audit log
            AuditLogger::log('order_created', $order);

            return response()->json([
                'status' => 'success',
                'message' => 'Order placed successfully.',
                'order' => $order->load('items', 'room'),
            ]);
        });
    }


    /**
     * Update Order Status (PATCH)
     */
    public function updateStatus(OrderStatusRequest $request, Order $order)
    {
        $validated = $request->validated();
        $newStatus = $validated['status'];

        // Authorization based on department
        $this->authorize('updateStatus', $order);

        return DB::transaction(function () use ($order, $newStatus, $request) {

            $previousStatus = $order->status;

            $order->update([
                'status' => $newStatus,
            ]);

            // Set timestamps for workflow
            if ($newStatus === OrderStatus::PROCESSING) {
                $order->processing_at = now();
            }
            if ($newStatus === OrderStatus::READY) {
                $order->ready_at = now();
            }
            if ($newStatus === OrderStatus::DELIVERED) {
                $order->delivered_at = now();
            }

            $order->save();

            // Staff action code (optional)
            if ($request->filled('action_code') && Auth::check()) {
                StaffActionService::validateAndRecord(
                    Auth::id(),
                    $request->input('action_code'),
                    'order_status_update',
                    $order
                );
            }

            // Audit log
            AuditLogger::log('order_status_changed', [
                'order_id' => $order->id,
                'from' => $previousStatus,
                'to' => $newStatus,
            ]);

            return response()->json([
                'status' => 'success',
                'message' => 'Order status updated.',
                'order' => $order->refresh()->load('items', 'room'),
            ]);
        });
    }

    /**
     * Order payment callback (Flutterwave)
     */
    public function paymentCallback(Request $request, Order $order)
    {
        $reference = $request->input('tx_ref');
        $status = $request->input('status');

        if ($status === 'successful' || $status === 'completed') {
            $order->update([
                'payment_status' => 'paid',
                'payment_reference' => $reference,
            ]);

            // Create a payment record for accounting
            $payment = $order->payments()->create([
                'amount' => $order->total,
                'currency' => 'NGN',
                'reference' => $reference,
                'status' => 'successful',
                'flutterwave_tx_ref' => $reference,
                'paid_at' => now(),
            ]);

            try {
                resolve(PaymentAccountingService::class)->handleSuccessful($payment);
            } catch (\Exception $e) {
                // Non-fatal; order remains recorded
            }

            return response()->json(['success' => true, 'message' => 'Order payment confirmed']);
        }

        $order->update(['payment_status' => 'failed']);
        return response()->json(['success' => false, 'message' => 'Payment failed'], 422);
    }
}
