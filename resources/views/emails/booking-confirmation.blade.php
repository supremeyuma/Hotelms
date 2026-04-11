<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Booking Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #334155; max-width: 640px; margin: 0 auto; padding: 24px; background-color: #f8fafc;">
    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden;">
        <div style="background-color: #0f172a; padding: 28px 32px; color: #ffffff;">
            <p style="margin: 0 0 8px; font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Reservation Confirmed</p>
            <h1 style="margin: 0; font-size: 30px; line-height: 1.2;">Your stay is booked</h1>
            <p style="margin: 12px 0 0; font-size: 15px; color: #cbd5e1;">Booking code: <strong style="color: #ffffff;">{{ $booking->booking_code }}</strong></p>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0; font-size: 16px;">Dear {{ $booking->guest_name }},</p>
            <p style="margin-bottom: 24px; color: #475569;">
                Your booking has been confirmed. We look forward to welcoming you.
            </p>

            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 24px; margin-bottom: 24px;">
                <h2 style="margin: 0 0 16px; font-size: 18px; color: #0f172a;">Booking Details</h2>
                <p style="margin: 0 0 10px;"><strong>Room Type:</strong> {{ $booking->roomType?->title ?? 'Room reservation' }}</p>
                <p style="margin: 0 0 10px;"><strong>Rooms Reserved:</strong> {{ $booking->quantity }}</p>
                <p style="margin: 0 0 10px;"><strong>Check-in:</strong> {{ optional($booking->check_in)->format('l, F j, Y') }}</p>
                <p style="margin: 0 0 10px;"><strong>Check-out:</strong> {{ optional($booking->check_out)->format('l, F j, Y') }}</p>
                <p style="margin: 0 0 10px;"><strong>Payment Status:</strong> {{ strtoupper($booking->payment_status ?? 'pending') }}</p>
                <p style="margin: 0;"><strong>Total Amount:</strong> NGN {{ number_format((float) $booking->total_amount, 2) }}</p>
            </div>

            @if($booking->rooms->isNotEmpty())
                <div style="margin-bottom: 24px;">
                    <h2 style="margin: 0 0 12px; font-size: 18px; color: #0f172a;">Reserved Rooms</h2>
                    @foreach($booking->rooms as $room)
                        <div style="padding: 14px 16px; border: 1px solid #e2e8f0; border-radius: 16px; margin-bottom: 10px; background-color: #ffffff;">
                            <p style="margin: 0; font-weight: 700; color: #0f172a;">{{ $room->display_name ?? $room->name ?? ('Room ' . $room->id) }}</p>
                            <p style="margin: 6px 0 0; font-size: 13px; color: #64748b;">
                                @if($room->floor)
                                    Floor {{ $room->floor }}
                                @else
                                    Reserved room
                                @endif
                            </p>
                        </div>
                    @endforeach
                </div>
            @endif

            @if($booking->special_requests)
                <div style="background-color: #eff6ff; border-left: 4px solid #2563eb; padding: 18px 20px; border-radius: 14px; margin-bottom: 24px;">
                    <p style="margin: 0 0 8px; font-weight: 700; color: #1e3a8a;">Special Requests</p>
                    <p style="margin: 0; color: #475569;">{{ $booking->special_requests }}</p>
                </div>
            @endif

            @if($booking->guest_phone || $booking->emergency_contact_name || $booking->emergency_contact_phone || $booking->purpose_of_stay)
                <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 24px; margin-bottom: 24px;">
                    <h2 style="margin: 0 0 16px; font-size: 18px; color: #0f172a;">Guest Information</h2>
                    @if($booking->guest_phone)
                        <p style="margin: 0 0 10px;"><strong>Guest Phone:</strong> {{ $booking->guest_phone }}</p>
                    @endif
                    @if($booking->purpose_of_stay)
                        <p style="margin: 0 0 10px;"><strong>Purpose of Stay:</strong> {{ $booking->purpose_of_stay }}</p>
                    @endif
                    @if($booking->emergency_contact_name || $booking->emergency_contact_phone)
                        <p style="margin: 0 0 10px;"><strong>Emergency Contact:</strong> {{ $booking->emergency_contact_name ?: 'Not provided' }}@if($booking->emergency_contact_phone) ({{ $booking->emergency_contact_phone }})@endif</p>
                    @endif
                </div>
            @endif

            <div style="background-color: #f0fdf4; border-left: 4px solid #16a34a; padding: 18px 20px; border-radius: 14px;">
                <p style="margin: 0 0 8px; font-weight: 700; color: #166534;">Arrival Note</p>
                <p style="margin: 0 0 14px; color: #475569;">
                    If your booking was paid online, you can complete pre-check-in from your confirmation page before arrival. Final room access will still be issued by the front desk after verification.
                </p>
                <a
                    href="{{ $confirmationUrl }}"
                    style="display: inline-block; padding: 10px 18px; border-radius: 999px; background-color: #166534; color: #ffffff; font-weight: 700; text-decoration: none;"
                >
                    Open confirmation page
                </a>
            </div>
        </div>
    </div>
</body>
</html>
