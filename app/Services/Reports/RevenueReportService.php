<?php
// app/Services/Reports/RevenueReportService.php

namespace App\Services\Reports;

use App\Models\Booking;
use Carbon\Carbon;
use Illuminate\Database\Eloquent\Builder;

class RevenueReportService
{
    public function filters(array $filters): array
    {
        $to = isset($filters['to']) && $filters['to']
            ? Carbon::parse($filters['to'])->endOfDay()
            : now()->endOfDay();

        $from = isset($filters['from']) && $filters['from']
            ? Carbon::parse($filters['from'])->startOfDay()
            : $to->copy()->subDays(29)->startOfDay();

        if ($from->gt($to)) {
            [$from, $to] = [$to->copy()->startOfDay(), $from->copy()->endOfDay()];
        }

        return [
            'search' => trim((string) ($filters['search'] ?? '')),
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    public function query(array $filters)
    {
        $filters = $this->filters($filters);

        return $this->baseQuery($filters)
            ->with(['user', 'roomType', 'room.roomType', 'rooms.roomType']);
    }
    
    public function chart(int $days = 30): array
    {
        $from = Carbon::now()->subDays($days);

        $data = Booking::selectRaw('DATE(created_at) as date, SUM(total_amount) as total')
            ->where('status', 'confirmed')
            ->whereDate('created_at', '>=', $from)
            ->groupBy('date')
            ->orderBy('date')
            ->get();

        return [
            'labels' => $data->pluck('date'),
            'values' => $data->pluck('total'),
        ];
    }

    public function summary(): array
    {
        return $this->summaryFor([]);
    }

    public function summaryFor(array $filters): array
    {
        $query = $this->baseQuery($this->filters($filters));
        $total = (clone $query)->sum('total_amount');
        $adr = (clone $query)->avg('total_amount');
        $bookings = (clone $query)->count();
        $todayRevenue = Booking::query()
            ->where('status', 'confirmed')
            ->whereDate('created_at', today())
            ->sum('total_amount');
        $latestBookingAt = (clone $query)->max('created_at');

        return [
            'revenue' => round($total, 2),
            'adr' => round($adr, 2),
            'bookings' => $bookings,
            'today_revenue' => round($todayRevenue, 2),
            'latest_booking_at' => $latestBookingAt ? Carbon::parse($latestBookingAt)->toDateString() : null,
        ];
    }

    protected function baseQuery(array $filters): Builder
    {
        return Booking::query()
            ->where('status', 'confirmed')
            ->when($filters['from'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '>=', $value))
            ->when($filters['to'] ?? null, fn ($query, $value) => $query->whereDate('created_at', '<=', $value))
            ->when($filters['search'] ?? null, function ($query, $search) {
                $query->where(function ($inner) use ($search) {
                    $inner->where('booking_code', 'like', "%{$search}%")
                        ->orWhere('guest_name', 'like', "%{$search}%")
                        ->orWhere('guest_email', 'like', "%{$search}%");
                });
            });
    }
}
