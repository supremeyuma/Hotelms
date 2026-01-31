<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Table Reservation Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    
    <header style="text-align: center; border-bottom: 2px solid #10B981; padding-bottom: 20px; margin-bottom: 30px;">
        <h1 style="color: #10B981; margin: 0;">🍽️ Table Reservation Confirmation</h1>
    </header>

    <main>
        <section style="background-color: #f8f9fa; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <h2 style="color: #1F2937; margin-top: 0;">Event Details</h2>
            <p><strong>Event:</strong> {{ $event->title }}</p>
            <p><strong>Date:</strong> {{ \Carbon\Carbon::parse($event->event_date)->format('l, F j, Y') }}</p>
            @if($event->start_time)
                <p><strong>Time:</strong> {{ \Carbon\Carbon::parse($event->start_time)->format('g:i A') }}</p>
            @endif
            @if($event->venue)
                <p><strong>Venue:</strong> {{ $event->venue }}</p>
            @endif
        </section>

        <section style="background-color: #D1FAE5; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #10B981;">
            <h3 style="color: #065F46; margin-top: 0;">🎉 Your Reservation</h3>
            <p><strong>Reservation #:</strong> {{ $reservation->id }}</p>
            <p><strong>Guest Name:</strong> {{ $reservation->guest_name }}</p>
            <p><strong>Email:</strong> {{ $reservation->guest_email }}</p>
            @if($reservation->guest_phone)
                <p><strong>Phone:</strong> {{ $reservation->guest_phone }}</p>
            @endif
            <p><strong>Table Number:</strong> {{ $reservation->table_number }}</p>
            <p><strong>Number of Guests:</strong> {{ $reservation->number_of_guests }}</p>
            <p><strong>Amount Paid:</strong> ₦{{ number_format($reservation->amount_paid, 2) }}</p>
        </section>

        <section style="text-align: center; margin-bottom: 30px;">
            <h3 style="color: #1F2937;">📱 Your QR Code</h3>
            <p style="color: #6B7280;">Present this QR code at venue for check-in</p>
            <div style="margin: 20px 0;">
                <img src="{{ asset($qrCodeUrl) }}" alt="QR Code" style="width: 200px; height: 200px; border: 2px solid #E5E7EB; border-radius: 8px;">
            </div>
            <p style="font-size: 14px; color: #6B7280;">QR Code has also been attached to this email</p>
        </section>

        @if($reservation->special_requests)
        <section style="background-color: #FEF3C7; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #F59E0B;">
            <h3 style="color: #92400E; margin-top: 0;">📝 Special Requests</h3>
            <p>{{ $reservation->special_requests }}</p>
        </section>
        @endif

        <section style="background-color: #F0FDF4; padding: 20px; border-radius: 8px; border-left: 4px solid #10B981;">
            <h3 style="color: #065F46; margin-top: 0;">📋 Important Information</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <li>Please arrive at least 15 minutes before your reservation time</li>
                <li>Bring a valid ID for verification</li>
                <li>Take a screenshot of this QR code as backup</li>
                <li>This reservation is confirmed and your table will be held for 30 minutes</li>
                @if($reservation->amount_paid > 0)
                    <li>Your table payment has been confirmed</li>
                @endif
            </ul>
        </section>
    </main>

    <footer style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #E5E7EB; color: #6B7280; font-size: 14px;">
        <p>Thank you for your reservation! We look forward to serving you at our venue.</p>
        <p style="margin-top: 10px;">
            <strong>Questions?</strong> Contact us at support@clubevents.com
        </p>
    </footer>

</body>
</html>