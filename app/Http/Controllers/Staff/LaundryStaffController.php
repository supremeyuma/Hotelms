<?php

// app/Http/Controllers/LaundryStaffController.php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Enums\LaundryStatus;
use App\Models\LaundryOrder;
use App\Services\LaundryOrderService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class LaundryStaffController extends Controller
{
    protected LaundryOrderService $service;

    public function __construct(LaundryOrderService $service)
    {
        $this->service = $service;
    }

    /**
     * Laundry dashboard (newest → oldest)
     */
    public function index()
    {
        $orders = LaundryOrder::with(['room', 'items.item', 'images', 'statusHistories'])
            ->orderByDesc('created_at')
            ->get();

        return Inertia::render('Staff/LaundryDashboard', compact('orders'));
    }

    /**
     * Update order status
     */
    public function updateStatus(Request $request, LaundryOrder $order)
    {
        $this->authorize('updateStatus', $order);

        $data = $request->validate([
            'status' => 'required|in:' . implode(',', array_column(LaundryStatus::cases(), 'value')),
        ]);

        $updated = $this->service->updateStatus($order, LaundryStatus::from($data['status']), $request->user()->id);

        return redirect()->back()->with('success', "Laundry order {$updated->order_code} status updated.");
    }
}
