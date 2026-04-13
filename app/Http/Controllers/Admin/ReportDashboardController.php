<?php
// app/Http/Controllers/Admin/ReportDashboardController.php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AccountingPeriod;
use App\Models\Booking;
use App\Models\Charge;
use App\Models\LaundryOrder;
use App\Models\MaintenanceTicket;
use App\Models\Order;
use App\Models\Payment;
use App\Models\Room;
use App\Services\BookingService;
use App\Services\Reports\OccupancyReportService;
use App\Services\Reports\StaffReportService;
use App\Services\Reports\InventoryReportService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class ReportDashboardController extends Controller
{
    public function index(Request $request, BookingService $bookingService)
    {
        $bookingService->reconcilePaidBookingStates();

        $mode = $request->string('mode')->toString() === 'range' ? 'range' : 'day';
        $day = $request->filled('day')
            ? Carbon::parse($request->string('day')->toString())
            : Carbon::today();

        $from = $request->filled('from')
            ? Carbon::parse($request->string('from')->toString())
            : $day->copy();

        $to = $request->filled('to')
            ? Carbon::parse($request->string('to')->toString())
            : $from->copy();

        if ($mode === 'day') {
            $from = $day->copy();
            $to = $day->copy();
        } elseif ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        $roomId = $request->integer('room_id') ?: null;
        $successfulPaymentStatuses = ['successful', 'completed', 'paid'];
        $periodStart = $from->copy()->startOfDay();
        $periodEnd = $to->copy()->endOfDay();
        $fromDate = $from->toDateString();
        $toDate = $to->toDateString();

        $roomOptions = Room::query()
            ->with('roomType:id,title')
            ->orderBy('name')
            ->get()
            ->map(fn (Room $room) => [
                'id' => $room->id,
                'label' => $this->roomLabel($room),
            ])
            ->values();

        $selectedRoom = $roomId
            ? Room::query()
                ->with('roomType:id,title')
                ->find($roomId)
            : null;

        $bookings = Booking::query()
            ->with([
                'user:id,name,email',
                'room.roomType:id,title',
                'roomType:id,title',
                'rooms.roomType:id,title',
                'charges:id,booking_id,room_id,description,amount,charge_date,created_at',
                'payments:id,booking_id,room_id,amount,amount_paid,method,status,reference,payment_reference,paid_at,created_at',
            ])
            ->where(function ($query) use ($fromDate, $toDate) {
                $query->whereDate('check_in', '<=', $toDate)
                    ->whereDate('check_out', '>=', $fromDate);
            })
            ->when($roomId, function ($query) use ($roomId) {
                $query->where(function ($bookingQuery) use ($roomId) {
                    $bookingQuery->where('room_id', $roomId)
                        ->orWhereHas('rooms', fn ($roomQuery) => $roomQuery->where('rooms.id', $roomId));
                });
            })
            ->latest('check_in')
            ->get();

        $bookingRows = $bookings->map(function (Booking $booking) use ($successfulPaymentStatuses) {
            $roomLabels = $booking->rooms
                ->map(fn (Room $room) => $this->roomLabel($room))
                ->filter()
                ->values();

            if ($roomLabels->isEmpty() && $booking->room) {
                $roomLabels = collect([$this->roomLabel($booking->room)]);
            }

            $actualCheckIn = $booking->rooms
                ->map(fn (Room $room) => $room->pivot?->checked_in_at)
                ->filter()
                ->sort()
                ->first();

            $actualCheckOut = $booking->rooms
                ->map(fn (Room $room) => $room->pivot?->checked_out_at)
                ->filter()
                ->sortDesc()
                ->first();

            $extraCharges = (float) $booking->charges->sum('amount');
            $paymentsReceived = (float) $booking->payments
                ->filter(fn (Payment $payment) => in_array($payment->status, $successfulPaymentStatuses, true))
                ->sum(fn (Payment $payment) => (float) ($payment->amount_paid ?? $payment->amount ?? 0));

            $baseBookingAmount = $this->effectiveBookingAmount($booking);
            $totalDue = max($baseBookingAmount + $extraCharges, 0);
            $outstanding = max($totalDue - $paymentsReceived, 0);
            $paymentStatus = $this->reportPaymentStatus(
                storedStatus: $booking->payment_status,
                amountDue: $totalDue,
                paymentsReceived: $paymentsReceived,
            );
            $guestCount = (int) ($booking->guests ?: (($booking->adults ?? 0) + ($booking->children ?? 0)) ?: 1);

            return [
                'id' => $booking->id,
                'booking_code' => $booking->booking_code,
                'guest_name' => $booking->guest_name ?: $booking->user?->name ?: 'Walk-in guest',
                'guest_email' => $booking->guest_email ?: $booking->user?->email,
                'guest_phone' => $booking->guest_phone,
                'rooms' => $roomLabels->all(),
                'room_summary' => $roomLabels->join(', '),
                'guests' => $guestCount,
                'status' => $booking->status,
                'payment_status' => $paymentStatus,
                'payment_method' => $booking->payment_method ?: 'Not recorded',
                'check_in' => optional($booking->check_in)?->toDateString(),
                'check_out' => optional($booking->check_out)?->toDateString(),
                'actual_check_in' => optional($actualCheckIn)?->toIso8601String(),
                'actual_check_out' => optional($actualCheckOut)?->toIso8601String(),
                'booked_amount' => round($baseBookingAmount, 2),
                'extra_charges' => round($extraCharges, 2),
                'total_due' => round($totalDue, 2),
                'payments_received' => round($paymentsReceived, 2),
                'outstanding_balance' => round($outstanding, 2),
                'created_at' => optional($booking->created_at)?->toIso8601String(),
            ];
        })->values();

        $charges = Charge::query()
            ->with([
                'booking:id,booking_code,guest_name,guest_email,guest_phone,user_id',
                'booking.user:id,name,email',
                'room.roomType:id,title',
            ])
            ->where(function ($query) use ($fromDate, $toDate) {
                $query->whereBetween('charge_date', [$fromDate, $toDate])
                    ->orWhere(function ($nested) use ($fromDate, $toDate) {
                        $nested->whereNull('charge_date')
                            ->whereBetween(DB::raw('DATE(created_at)'), [$fromDate, $toDate]);
                    });
            })
            ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
            ->latest('created_at')
            ->get()
            ->map(fn (Charge $charge) => [
                'id' => $charge->id,
                'booking_code' => $charge->booking?->booking_code,
                'guest_name' => $charge->booking?->guest_name ?: $charge->booking?->user?->name ?: 'Walk-in guest',
                'description' => $charge->description,
                'amount' => round((float) $charge->amount, 2),
                'status' => $charge->status ?: 'unpaid',
                'payment_mode' => $charge->payment_mode ?: 'postpaid',
                'room_label' => $this->roomLabel($charge->room),
                'recorded_at' => optional($charge->charge_date ?? $charge->created_at)?->toDateString(),
                'created_at' => optional($charge->created_at)?->toIso8601String(),
            ])
            ->values();

        $payments = Payment::query()
            ->with([
                'booking:id,booking_code,guest_name,guest_email,guest_phone,user_id',
                'booking.user:id,name,email',
                'room.roomType:id,title',
            ])
            ->whereIn('status', $successfulPaymentStatuses)
            ->where(function ($query) use ($periodStart, $periodEnd) {
                $query->whereBetween('paid_at', [$periodStart, $periodEnd])
                    ->orWhere(function ($nested) use ($periodStart, $periodEnd) {
                        $nested->whereNull('paid_at')
                            ->whereBetween('created_at', [$periodStart, $periodEnd]);
                    });
            })
            ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
            ->latest(DB::raw('COALESCE(paid_at, created_at)'))
            ->get()
            ->map(fn (Payment $payment) => [
                'id' => $payment->id,
                'booking_code' => $payment->booking?->booking_code,
                'guest_name' => $payment->booking?->guest_name ?: $payment->booking?->user?->name ?: 'Walk-in guest',
                'amount' => round((float) ($payment->amount_paid ?? $payment->amount ?? 0), 2),
                'method' => $payment->method ?: 'Not recorded',
                'status' => $payment->status,
                'provider' => $payment->provider ?: 'Manual',
                'reference' => $payment->payment_reference ?: $payment->reference ?: $payment->transaction_ref,
                'room_label' => $this->roomLabel($payment->room),
                'recorded_at' => optional($payment->paid_at ?? $payment->created_at)?->toIso8601String(),
            ])
            ->values();

        $hotelSummary = [
            'bookings' => $bookingRows->count(),
            'distinct_rooms' => $bookingRows->flatMap(fn (array $booking) => $booking['rooms'])->filter()->unique()->count(),
            'guest_volume' => $bookingRows->sum('guests'),
            'arrivals' => $bookings->filter(fn (Booking $booking) => optional($booking->check_in)?->betweenIncluded($from, $to))->count(),
            'departures' => $bookings->filter(fn (Booking $booking) => optional($booking->check_out)?->betweenIncluded($from, $to))->count(),
            'booking_value' => round((float) $bookingRows->sum('booked_amount'), 2),
            'charges_posted' => round((float) $charges->sum('amount'), 2),
            'payments_received' => round((float) $payments->sum('amount'), 2),
            'outstanding_exposure' => round((float) $bookingRows->sum('outstanding_balance'), 2),
        ];

        $roomReport = null;

        if ($selectedRoom) {
            $roomReport = [
                'label' => $this->roomLabel($selectedRoom),
                'bookings' => $bookingRows->count(),
                'guest_volume' => $bookingRows->sum('guests'),
                'booking_value' => round((float) $bookingRows->sum('booked_amount'), 2),
                'charges_posted' => round((float) $charges->sum('amount'), 2),
                'payments_received' => round((float) $payments->sum('amount'), 2),
                'outstanding_exposure' => round((float) $bookingRows->sum('outstanding_balance'), 2),
                'maintenance_tickets' => MaintenanceTicket::query()
                    ->where('room_id', $selectedRoom->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'service_orders' => Order::query()
                    ->where('room_id', $selectedRoom->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'laundry_orders' => LaundryOrder::query()
                    ->where('room_id', $selectedRoom->id)
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
            ];
        }

        $departmentReports = collect([
            [
                'name' => 'Front Desk',
                'summary' => 'Stay activity and guest movement during the selected period.',
                'primary_metric' => $hotelSummary['bookings'],
                'primary_label' => 'Bookings in scope',
                'secondary_metric' => $hotelSummary['arrivals'],
                'secondary_label' => 'Arrivals',
                'tertiary_metric' => $hotelSummary['departures'],
                'tertiary_label' => 'Departures',
                'amount' => $hotelSummary['booking_value'],
                'amount_label' => 'Booking value',
            ],
            [
                'name' => 'Kitchen',
                'summary' => 'Food service activity captured from room orders.',
                'primary_metric' => Order::query()
                    ->where('service_area', 'kitchen')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'primary_label' => 'Orders',
                'secondary_metric' => Order::query()
                    ->where('service_area', 'kitchen')
                    ->whereIn('status', ['pending', 'processing'])
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'secondary_label' => 'Open',
                'tertiary_metric' => Order::query()
                    ->where('service_area', 'kitchen')
                    ->where('payment_status', 'paid')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'tertiary_label' => 'Paid',
                'amount' => round((float) Order::query()
                    ->where('service_area', 'kitchen')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->sum('total'), 2),
                'amount_label' => 'Order value',
            ],
            [
                'name' => 'Bar',
                'summary' => 'Bar orders and payment capture during the selected period.',
                'primary_metric' => Order::query()
                    ->where('service_area', 'bar')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'primary_label' => 'Orders',
                'secondary_metric' => Order::query()
                    ->where('service_area', 'bar')
                    ->whereIn('status', ['pending', 'processing'])
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'secondary_label' => 'Open',
                'tertiary_metric' => Order::query()
                    ->where('service_area', 'bar')
                    ->where('payment_status', 'paid')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'tertiary_label' => 'Paid',
                'amount' => round((float) Order::query()
                    ->where('service_area', 'bar')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->sum('total'), 2),
                'amount_label' => 'Order value',
            ],
            [
                'name' => 'Laundry',
                'summary' => 'Laundry pickup and completion activity for the selected period.',
                'primary_metric' => LaundryOrder::query()
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'primary_label' => 'Orders',
                'secondary_metric' => LaundryOrder::query()
                    ->whereNotIn('status', ['delivered', 'cancelled'])
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'secondary_label' => 'Open',
                'tertiary_metric' => LaundryOrder::query()
                    ->where('status', 'delivered')
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'tertiary_label' => 'Delivered',
                'amount' => round((float) LaundryOrder::query()
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->sum('total_amount'), 2),
                'amount_label' => 'Order value',
            ],
            [
                'name' => 'Maintenance',
                'summary' => 'Issue intake and unresolved tickets within the reporting window.',
                'primary_metric' => MaintenanceTicket::query()
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'primary_label' => 'Tickets',
                'secondary_metric' => MaintenanceTicket::query()
                    ->whereNotIn('status', ['resolved', 'closed', 'completed'])
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'secondary_label' => 'Open',
                'tertiary_metric' => MaintenanceTicket::query()
                    ->whereIn('status', ['resolved', 'closed', 'completed'])
                    ->when($roomId, fn ($query) => $query->where('room_id', $roomId))
                    ->whereBetween('created_at', [$periodStart, $periodEnd])
                    ->count(),
                'tertiary_label' => 'Resolved',
                'amount' => null,
                'amount_label' => null,
            ],
            [
                'name' => 'Finance',
                'summary' => 'Posted monetary activity tied to the selected hotel period.',
                'primary_metric' => $payments->count(),
                'primary_label' => 'Payments',
                'secondary_metric' => $charges->count(),
                'secondary_label' => 'Charges',
                'tertiary_metric' => $bookingRows->filter(fn (array $booking) => $booking['outstanding_balance'] > 0)->count(),
                'tertiary_label' => 'Open balances',
                'amount' => $hotelSummary['payments_received'],
                'amount_label' => 'Cash in',
            ],
        ])->values();

        return Inertia::render('Admin/Reports/Hub', [
            'filters' => [
                'mode' => $mode,
                'day' => $day->toDateString(),
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'room_id' => $roomId,
            ],
            'period' => [
                'label' => $mode === 'day'
                    ? $day->format('l, d M Y')
                    : $from->format('d M Y') . ' to ' . $to->format('d M Y'),
                'mode' => $mode,
                'from' => $from->toDateString(),
                'to' => $to->toDateString(),
                'selected_room' => $selectedRoom ? [
                    'id' => $selectedRoom->id,
                    'label' => $this->roomLabel($selectedRoom),
                ] : null,
            ],
            'roomOptions' => $roomOptions,
            'hotelSummary' => $hotelSummary,
            'roomReport' => $roomReport,
            'departmentReports' => $departmentReports,
            'bookings' => $bookingRows,
            'charges' => $charges,
            'payments' => $payments,
            'reportLinks' => [
                [
                    'label' => 'Operations Snapshot',
                    'href' => route('admin.reports.operations'),
                    'description' => 'Open the legacy occupancy, staff, and inventory dashboard.',
                ],
                [
                    'label' => 'Occupancy Report',
                    'href' => route('admin.reports.occupancy'),
                    'description' => 'Review stay volume and occupancy trend in detail.',
                ],
                [
                    'label' => 'Staff Report',
                    'href' => route('admin.reports.staff'),
                    'description' => 'Inspect staffing coverage and team filters.',
                ],
                [
                    'label' => 'Inventory Report',
                    'href' => route('admin.reports.inventory'),
                    'description' => 'Check stock movement and inventory pressure.',
                ],
                [
                    'label' => 'Executive Overview',
                    'href' => route('admin.reports.executive-overview'),
                    'description' => 'Open the executive exception and department overview.',
                ],
            ],
        ]);
    }

    public function operations(
        OccupancyReportService $occupancy,
        StaffReportService $staff,
        InventoryReportService $inventory
    ) {
        return Inertia::render('Admin/Reports/Dashboard', [
            'mode' => 'operations',
            'title' => 'Operations Reports',
            'kpis' => [
                'occupancy' => $occupancy->summary(),
                'staff'     => $staff->summary(),
                'inventory' => $inventory->summary(),
            ],
            'links' => [
                'primary' => route('admin.reports.occupancy'),
                'secondary' => route('admin.reports.staff'),
                'tertiary' => route('admin.reports.inventory'),
            ],
            'charts' => [
                [
                    'title' => 'Occupancy Trend',
                    'endpoint' => '/admin/reports/charts/occupancy',
                ],
                [
                    'title' => 'Inventory Movement',
                    'endpoint' => '/admin/reports/charts/inventory',
                ],
            ],
        ]);
    }

    public function finance(
    ) {
        $summaryFrom = Carbon::now()->subDays(29)->startOfDay();
        $today = Carbon::today();

        $chargeSummaryQuery = Charge::query()
            ->whereDate('charge_date', '>=', $summaryFrom->toDateString());

        $paymentSummaryQuery = $this->successfulPaymentsQuery()
            ->where(function ($query) use ($summaryFrom) {
                $query->whereDate('paid_at', '>=', $summaryFrom->toDateString())
                    ->orWhere(function ($nested) use ($summaryFrom) {
                        $nested->whereNull('paid_at')
                            ->whereDate('created_at', '>=', $summaryFrom->toDateString());
                    });
            });

        $activeBookings = Booking::query()
            ->with(['user:id,name,email', 'charges:id,booking_id,amount', 'payments:id,booking_id,amount,amount_paid,status'])
            ->whereNotIn('status', ['cancelled', 'completed'])
            ->get();

        $outstandingBookings = $activeBookings
            ->map(function (Booking $booking) {
                $chargeTotal = (float) $booking->charges->sum(fn (Charge $charge) => (float) $charge->amount);
                $paymentTotal = (float) $booking->payments
                    ->filter(fn (Payment $payment) => in_array($payment->status, ['successful', 'completed'], true))
                    ->sum(fn (Payment $payment) => (float) ($payment->amount_paid ?? $payment->amount ?? 0));

                return [
                    'booking_id' => $booking->id,
                    'outstanding' => round(max($chargeTotal - $paymentTotal, 0), 2),
                ];
            })
            ->filter(fn (array $booking) => $booking['outstanding'] > 0)
            ->values();

        $recentCharges = Charge::query()
            ->with(['booking:id,booking_code,guest_name,guest_email', 'room:id,name,room_number'])
            ->latest('created_at')
            ->limit(12)
            ->get()
            ->map(function (Charge $charge) {
                return [
                    'id' => 'charge-' . $charge->id,
                    'kind' => 'charge',
                    'label' => $charge->description ?: 'Charge posted',
                    'booking_code' => $charge->booking?->booking_code,
                    'guest' => $charge->booking?->guest_name ?: $charge->booking?->guest_email ?: 'Guest',
                    'room' => $charge->room?->name ?: $charge->room?->room_number ?: 'Unassigned room',
                    'amount' => round((float) $charge->amount, 2),
                    'occurred_at' => optional($charge->created_at)?->toIso8601String(),
                ];
            });

        $recentPayments = $this->successfulPaymentsQuery()
            ->with(['booking:id,booking_code,guest_name,guest_email', 'room:id,name,room_number'])
            ->latest(DB::raw('COALESCE(paid_at, created_at)'))
            ->limit(12)
            ->get()
            ->map(function (Payment $payment) {
                return [
                    'id' => 'payment-' . $payment->id,
                    'kind' => 'payment',
                    'label' => 'Payment received',
                    'booking_code' => $payment->booking?->booking_code,
                    'guest' => $payment->booking?->guest_name ?: $payment->booking?->guest_email ?: 'Guest',
                    'room' => $payment->room?->name ?: $payment->room?->room_number ?: 'Unassigned room',
                    'amount' => round((float) ($payment->amount_paid ?? $payment->amount ?? 0), 2),
                    'occurred_at' => optional($payment->paid_at ?? $payment->created_at)?->toIso8601String(),
                ];
            });

        $recentTransactions = $recentCharges
            ->concat($recentPayments)
            ->sortByDesc('occurred_at')
            ->take(12)
            ->values();

        return Inertia::render('Admin/Reports/Dashboard', [
            'mode' => 'finance',
            'title' => 'Finance Dashboard',
            'kpis' => [
                'charges' => [
                    'count' => (clone $chargeSummaryQuery)->count(),
                    'total' => round((float) (clone $chargeSummaryQuery)->sum('amount'), 2),
                ],
                'payments' => [
                    'count' => (clone $paymentSummaryQuery)->count(),
                    'total' => round((float) (clone $paymentSummaryQuery)->sum(DB::raw('COALESCE(amount_paid, amount)')), 2),
                    'today_total' => round((float) $this->successfulPaymentsQuery()
                        ->where(function ($query) use ($today) {
                            $query->whereDate('paid_at', $today->toDateString())
                                ->orWhere(function ($nested) use ($today) {
                                    $nested->whereNull('paid_at')
                                        ->whereDate('created_at', $today->toDateString());
                                });
                        })
                        ->sum(DB::raw('COALESCE(amount_paid, amount)')), 2),
                ],
                'outstanding' => [
                    'count' => $outstandingBookings->count(),
                    'total' => round((float) $outstandingBookings->sum('outstanding'), 2),
                ],
                'periods' => [
                    'open' => AccountingPeriod::query()->where('is_closed', false)->count(),
                ],
            ],
            'links' => [
                'primary' => route('finance.outstanding-balances.index', ['view' => 'booking']),
                'secondary' => route('finance.audit.index'),
                'tertiary' => route('finance.accounting-periods.index'),
                'quaternary' => route('finance.reports.daily-revenue'),
            ],
            'recentTransactions' => $recentTransactions,
            'charts' => [
                [
                    'title' => 'Revenue Trend',
                    'endpoint' => '/finance/reports/charts/revenue',
                ],
            ],
        ]);
    }

    protected function successfulPaymentsQuery()
    {
        return Payment::query()->whereIn('status', ['successful', 'completed']);
    }

    protected function roomLabel(?Room $room): string
    {
        if (! $room) {
            return 'Unassigned room';
        }

        return trim(collect([
            $room->roomType?->title,
            $room->display_name,
            $room->name ?: $room->room_number ?: $room->code,
        ])->filter()->implode(' - '));
    }

    protected function effectiveBookingAmount(Booking $booking): float
    {
        $details = is_array($booking->details) ? $booking->details : [];
        $override = is_array($details['price_override'] ?? null) ? $details['price_override'] : null;
        $discount = is_array($details['discount'] ?? null) ? $details['discount'] : null;
        $discountPricing = is_array($discount['pricing'] ?? null) ? $discount['pricing'] : null;

        $overrideAmount = isset($override['override_amount'])
            ? round((float) $override['override_amount'], 2)
            : null;

        if ($overrideAmount !== null) {
            return $overrideAmount;
        }

        $discountedTotal = isset($discountPricing['total'])
            ? round((float) $discountPricing['total'], 2)
            : null;

        if ($discountedTotal !== null) {
            return $discountedTotal;
        }

        $storedTotal = $booking->total_amount !== null
            ? round((float) $booking->total_amount, 2)
            : null;

        if ($storedTotal !== null) {
            return $storedTotal;
        }

        $nightlyRate = (float) ($booking->nightly_rate ?: $booking->roomType?->base_price ?: $booking->room?->roomType?->base_price ?: 0);
        $roomCount = max((int) ($booking->quantity ?: $booking->rooms->count() ?: 1), 1);
        $nights = $booking->check_in && $booking->check_out
            ? max($booking->check_in->diffInDays($booking->check_out), 1)
            : 1;

        return round($nightlyRate * $roomCount * $nights, 2);
    }

    protected function reportPaymentStatus(?string $storedStatus, float $amountDue, float $paymentsReceived): string
    {
        $normalizedStoredStatus = strtolower(trim((string) $storedStatus));

        if ($amountDue <= 0) {
            return $paymentsReceived > 0 ? 'paid' : ($normalizedStoredStatus !== '' ? $normalizedStoredStatus : 'not_required');
        }

        if ($paymentsReceived >= $amountDue) {
            return 'paid';
        }

        if ($paymentsReceived > 0 && $paymentsReceived < $amountDue) {
            return 'partial';
        }

        return $normalizedStoredStatus !== '' ? $normalizedStoredStatus : 'unpaid';
    }
}
