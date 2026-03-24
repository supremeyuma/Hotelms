<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Booking;
use App\Models\Room;
use App\Services\RoomBillingService;

class OutstandingBalancesController extends Controller
{
    public function __construct(
        protected RoomBillingService $billingService
    ) {}

    public function index(Request $request)
    {
        $view = $request->input('view', 'room'); // 'room' or 'booking'
        $search = $request->input('search', '');

        if ($view === 'booking') {
            $data = $this->getBookingOutstandingBalances($search);
        } else {
            $data = $this->getRoomOutstandingBalances($search);
        }

        return Inertia::render('Admin/OutstandingBalances', [
            'view' => $view,
            'search' => $search,
            'summary' => $data['summary'],
            'balances' => $data['balances'],
            'routePrefix' => 'finance',
        ]);
    }

    protected function getRoomOutstandingBalances(string $search): array
    {
        $query = Room::with(['booking', 'booking.user'])
            ->whereHas('booking', function ($q) {
                $q->whereNotIn('status', ['cancelled', 'completed']);
            })
            ->where('status', '!=', 'available');

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('room_number', 'like', "%{$search}%")
                  ->orWhere('name', 'like', "%{$search}%")
                  ->orWhereHas('booking', function ($bq) use ($search) {
                      $bq->where('booking_code', 'like', "%{$search}%")
                        ->orWhereHas('user', function ($uq) use ($search) {
                            $uq->where('name', 'like', "%{$search}%")
                               ->orWhere('email', 'like', "%{$search}%");
                        });
                  });
            });
        }

        $rooms = $query->get();

        $outstandingRooms = $rooms->filter(function ($room) {
            return $this->billingService->outstanding($room) > 0;
        });

        $totalOutstanding = $outstandingRooms->sum(function ($room) {
            return $this->billingService->outstanding($room);
        });

        return [
            'summary' => [
                'total_outstanding' => round($totalOutstanding, 2),
                'total_rooms' => $outstandingRooms->count(),
                'average_outstanding' => $outstandingRooms->count() > 0 
                    ? round($totalOutstanding / $outstandingRooms->count(), 2) 
                    : 0,
            ],
            'balances' => $outstandingRooms->map(function ($room) {
                $outstanding = $this->billingService->outstanding($room);
                return [
                    'room_id' => $room->id,
                    'room_number' => $room->room_number,
                    'room_name' => $room->name,
                    'booking' => [
                        'id' => $room->booking->id,
                        'code' => $room->booking->booking_code,
                        'guest' => $room->booking->user ? $room->booking->user->name : 'Guest',
                        'email' => $room->booking->guest_email,
                        'check_in' => $room->booking->check_in,
                        'check_out' => $room->booking->check_out,
                        'status' => $room->booking->status,
                    ],
                    'outstanding' => round($outstanding, 2),
                    'room_status' => $room->status,
                    'days_occupied' => $room->booking->check_in 
                        ? now()->diffInDays($room->booking->check_in) 
                        : 0,
                ];
            })->values(),
        ];
    }

    protected function getBookingOutstandingBalances(string $search): array
    {
        $query = Booking::with(['user', 'rooms'])
            ->whereNotIn('status', ['cancelled', 'completed']);

        if ($search) {
            $query->where(function ($q) use ($search) {
                $q->where('booking_code', 'like', "%{$search}%")
                  ->orWhereHas('user', function ($uq) use ($search) {
                      $uq->where('name', 'like', "%{$search}%")
                         ->orWhere('email', 'like', "%{$search}%");
                  });
            });
        }

        $bookings = $query->get();

        $outstandingBookings = $bookings->filter(function ($booking) {
            $totalOutstanding = $booking->rooms->sum(function ($room) {
                return $this->billingService->outstanding($room);
            });
            return $totalOutstanding > 0;
        });

        $totalOutstanding = $outstandingBookings->sum(function ($booking) {
            return $booking->rooms->sum(function ($room) {
                return $this->billingService->outstanding($room);
            });
        });

        return [
            'summary' => [
                'total_outstanding' => round($totalOutstanding, 2),
                'total_bookings' => $outstandingBookings->count(),
                'average_outstanding' => $outstandingBookings->count() > 0 
                    ? round($totalOutstanding / $outstandingBookings->count(), 2) 
                    : 0,
            ],
            'balances' => $outstandingBookings->map(function ($booking) {
                $bookingOutstanding = $booking->rooms->sum(function ($room) {
                    return $this->billingService->outstanding($room);
                });

                return [
                    'booking_id' => $booking->id,
                    'booking_code' => $booking->booking_code,
                    'guest' => $booking->user ? $booking->user->name : 'Guest',
                    'email' => $booking->guest_email,
                    'check_in' => $booking->check_in,
                    'check_out' => $booking->check_out,
                    'status' => $booking->status,
                    'outstanding' => round($bookingOutstanding, 2),
                    'rooms' => $booking->rooms->map(function ($room) {
                        $roomOutstanding = $this->billingService->outstanding($room);
                        return [
                            'id' => $room->id,
                            'number' => $room->room_number,
                            'name' => $room->name,
                            'outstanding' => round($roomOutstanding, 2),
                            'status' => $room->status,
                        ];
                    }),
                    'total_rooms' => $booking->rooms->count(),
                    'rooms_with_balance' => $booking->rooms->filter(function ($room) {
                        return $this->billingService->outstanding($room) > 0;
                    })->count(),
                ];
            })->values(),
        ];
    }

    public function export(Request $request)
    {
        $view = $request->input('view', 'room');

        if ($view === 'booking') {
            $data = $this->getBookingOutstandingBalances('');
        } else {
            $data = $this->getRoomOutstandingBalances('');
        }

        $headers = $view === 'booking'
            ? ['Booking Code', 'Guest', 'Email', 'Status', 'Outstanding', 'Rooms With Balance']
            : ['Room Number', 'Room Name', 'Booking Code', 'Guest', 'Email', 'Outstanding', 'Room Status'];

        return response()->streamDownload(function () use ($data, $view, $headers) {
            $handle = fopen('php://output', 'w');
            fputcsv($handle, $headers);

            foreach ($data['balances'] as $item) {
                if ($view === 'booking') {
                    fputcsv($handle, [
                        $item['booking_code'],
                        $item['guest'],
                        $item['email'],
                        $item['status'],
                        $item['outstanding'],
                        $item['rooms_with_balance'],
                    ]);
                    continue;
                }

                fputcsv($handle, [
                    $item['room_number'],
                    $item['room_name'],
                    $item['booking']['code'],
                    $item['booking']['guest'],
                    $item['booking']['email'],
                    $item['outstanding'],
                    $item['room_status'],
                ]);
            }

            fclose($handle);
        }, "outstanding-balances-{$view}.csv", [
            'Content-Type' => 'text/csv',
        ]);
    }
}
