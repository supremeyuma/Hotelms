<?php
// app/Services/Reports/OccupancyReportService.php

namespace App\Services\Reports;

use App\Models\Booking;
use App\Models\Room;
use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Illuminate\Support\Collection;

class OccupancyReportService
{
    // Booking statuses that should be counted as occupancy
    // Excludes: pending_payment, cancelled, checked_out
    protected array $reportableStatuses = [
        'pending',
        'confirmed',
        'active',
        'checked_in',
    ];

    public function filters(array $filters): array
    {
        $to = isset($filters['to']) && $filters['to']
            ? Carbon::parse($filters['to'])->startOfDay()
            : today()->startOfDay();

        $from = isset($filters['from']) && $filters['from']
            ? Carbon::parse($filters['from'])->startOfDay()
            : $to->copy()->subDays(13);

        if ($from->gt($to)) {
            [$from, $to] = [$to, $from];
        }

        return [
            'from' => $from->toDateString(),
            'to' => $to->toDateString(),
        ];
    }

    public function query(array $filters): Collection
    {
        $range = $this->filters($filters);
        $from = Carbon::parse($range['from'])->startOfDay();
        $to = Carbon::parse($range['to'])->startOfDay();
        $roomCount = Room::count();
        $bookings = $this->bookingQuery($from, $to)->get();

        return collect(CarbonPeriod::create($from, $to))
            ->map(function (Carbon $date) use ($bookings, $roomCount) {
                $occupiedRooms = 0;
                $occupiedBookings = 0;
                $arrivals = 0;
                $departures = 0;

                foreach ($bookings as $booking) {
                    $units = max((int) ($booking->quantity ?: 1), 1);

                    if ($booking->check_in?->isSameDay($date)) {
                        $arrivals += $units;
                    }

                    if ($booking->check_out?->isSameDay($date)) {
                        $departures += $units;
                    }

                    if ($booking->check_in && $booking->check_out && $date->betweenIncluded($booking->check_in, $booking->check_out)) {
                        $occupiedRooms += $units;
                        $occupiedBookings++;
                    }
                }

                return [
                    'date' => $date->toDateString(),
                    'bookings' => $occupiedBookings,
                    'occupied_rooms' => $occupiedRooms,
                    'available_rooms' => max($roomCount - $occupiedRooms, 0),
                    'arrivals' => $arrivals,
                    'departures' => $departures,
                    'occupancy_rate' => $roomCount > 0
                        ? round(($occupiedRooms / $roomCount) * 100, 2)
                        : 0,
                ];
            })
            ->values();
    }

    public function chart(int $days = 30): array
    {
        $rows = $this->query([
            'from' => today()->subDays(max($days - 1, 0))->toDateString(),
            'to' => today()->toDateString(),
        ]);

        return [
            'labels' => $rows->pluck('date'),
            'values' => $rows->pluck('occupancy_rate'),
        ];
    }

    public function summary(): array
    {
        $rooms = Room::count();
        $today = today()->startOfDay();
        $bookings = $this->bookingQuery($today, $today)->get();
        $occupiedRooms = 0;
        $occupiedBookings = 0;
        $arrivals = 0;
        $departures = 0;

        foreach ($bookings as $booking) {
            $units = max((int) ($booking->quantity ?: 1), 1);

            if ($booking->check_in?->isSameDay($today)) {
                $arrivals += $units;
            }

            if ($booking->check_out?->isSameDay($today)) {
                $departures += $units;
            }

            if ($booking->check_in && $booking->check_out && $today->betweenIncluded($booking->check_in, $booking->check_out)) {
                $occupiedRooms += $units;
                $occupiedBookings++;
            }
        }

        $rate = $rooms > 0
            ? round(($occupiedRooms / $rooms) * 100, 2)
            : 0;

        return [
            'occupancy' => $rate,
            'occupied_rooms' => $occupiedRooms,
            'occupied_bookings' => $occupiedBookings,
            'available_rooms' => max($rooms - $occupiedRooms, 0),
            'arrivals_today' => $arrivals,
            'departures_today' => $departures,
            'total_rooms' => $rooms,
        ];
    }

    protected function bookingQuery(Carbon $from, Carbon $to)
    {
        return Booking::query()
            ->select(['id', 'check_in', 'check_out', 'quantity', 'status'])
            ->whereIn('status', $this->reportableStatuses)
            ->whereDate('check_in', '<=', $to->toDateString())
            ->whereDate('check_out', '>=', $from->toDateString());
    }
}
