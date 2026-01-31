<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Ticket Confirmation</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #333; max-width: 600px; margin: 0 auto; padding: 20px;">
    
    <header style="text-align: center; border-bottom: 2px solid #3B82F6; padding-bottom: 20px; margin-bottom: 30px;">
        <h1 style="color: #3B82F6; margin: 0;">🎫 Event Ticket Confirmation</h1>
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

        <section style="background-color: #FEF3C7; padding: 20px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #F59E0B;">
            <h3 style="color: #92400E; margin-top: 0;">🎉 Your Tickets</h3>
            <p><strong>Ticket Type:</strong> {{ $ticket->ticketType->name ?? 'General Admission' }}</p>
            <p><strong>Quantity:</strong> {{ $ticket->quantity }}</p>
            <p><strong>Guest Name:</strong> {{ $ticket->guest_name }}</p>
            <p><strong>Email:</strong> {{ $ticket->guest_email }}</p>
            @if($ticket->guest_phone)
                <p><strong>Phone:</strong> {{ $ticket->guest_phone }}</p>
            @endif
            <p><strong>Total Paid:</strong> ₦{{ number_format($totalAmount, 2) }}</p>
        </section>

        <section style="text-align: center; margin-bottom: 30px;">
            <h3 style="color: #1F2937;">📱 Your QR Code</h3>
            <p style="color: #6B7280;">Present this QR code at the venue for check-in</p>
            <div style="margin: 20px 0;">
                <img src="{{ asset($qrCodeUrl) }}" alt="QR Code" style="width: 200px; height: 200px; border: 2px solid #E5E7EB; border-radius: 8px;">
            </div>
            <p style="font-size: 14px; color: #6B7280;">QR Code has also been attached to this email</p>
        </section>

        <section style="background-color: #EFF6FF; padding: 20px; border-radius: 8px; border-left: 4px solid #3B82F6;">
            <h3 style="color: #1E40AF; margin-top: 0;">📋 Important Information</h3>
            <ul style="margin: 0; padding-left: 20px;">
                <li>Please arrive at least 30 minutes before the event starts</li>
                <li>Bring a valid ID for verification</li>
                <li>Take a screenshot of this QR code as backup</li>
                <li>This ticket is non-refundable unless the event is cancelled</li>
            </ul>
        </section>
    </main>

    <footer style="text-align: center; margin-top: 40px; padding-top: 20px; border-top: 1px solid #E5E7EB; color: #6B7280; font-size: 14px;">
        <p>Thank you for your purchase! We look forward to seeing you at the event.</p>
        <p style="margin-top: 10px;">
            <strong>Questions?</strong> Contact us at support@clubevents.com
        </p>
    </footer>

</body>
</html>