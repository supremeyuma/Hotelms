<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\LaundryOrder;
use App\Enums\LaundryStatus;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Services\LaundryOrderService;

class LaundryStaffController extends Controller
{
    public function index(Request $request)
    {
        $status = $request->query('status');

        $orders = LaundryOrder::with([
            'room',
            'items.item',
            'images',
            'statusHistories.changer',
        ])
        ->when($status, fn ($q) => $q->where('status', $status))
        ->latest()
        ->get();

        return Inertia::render('Staff/Laundry/Dashboard', [
            'orders' => $orders,
            'statuses' => LaundryStatus::cases(),
            'activeStatus' => $status,
        ]);
    }

    public function show(LaundryOrder $order)
    {
        $order->load([
            'room',
            'items.item',
            'images',
            'statusHistories.changer',
        ]);
        //dd(LaundryStatus::cases());

        return Inertia::render('Staff/Laundry/Show', [
            'order' => $order,
            'statuses' => collect(LaundryStatus::cases())
                ->map(fn ($s) => $s->value)
                ->values(),
        ]);
    }

    public function updateStatus(
    Request $request,
    LaundryOrder $order,
    LaundryOrderService $service
) {
    $request->validate([
        'status' => ['required', 'string'],
    ]);

    $newStatus = LaundryStatus::from($request->status);

    // Delegate ALL logic to service
    $service->updateStatus(
        $order,
        $newStatus,
        auth()->id()
    );

    return back();
}

    public function cancel(
        LaundryOrder $order,
        LaundryOrderService $service
    ) {
        abort_unless(
            in_array(
                LaundryStatus::CANCELLED,
                LaundryStatus::allowedTransitions($order->status),
                true
            ),
            403,
            'Invalid cancellation'
        );

        $service->updateStatus(
            $order,
            LaundryStatus::CANCELLED,
            auth()->id()
        );

        return back();
    }

    public function addImages(Request $request, LaundryOrder $order)
    {
        $request->validate([
            'images.*' => 'required|image|max:2048',
        ]);

        foreach ($request->file('images') as $file) {
            $order->images()->create([
                'path' => $file->store('laundry', 'public'),
            ]);
        }

        event(new \App\Events\LaundryOrderUpdated($order->fresh()));

        return back();
    }

    public function print(LaundryOrder $order)
    {
        $order->load(['room', 'items.item']);

        return Inertia::render('Staff/Laundry/Print', [
            'order' => $order,
        ]);
    }
}
