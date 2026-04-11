<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Mail Settings Test</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #0f172a; background: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #0f172a 0%, #065f46 100%); color: #ffffff; padding: 28px 32px;">
            <p style="margin: 0 0 8px; font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; opacity: 0.8;">Manager Settings</p>
            <h1 style="margin: 0; font-size: 28px; line-height: 1.2;">Mail delivery test successful</h1>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0;">This test message confirms that the current mail configuration for <strong>{{ $hotelName }}</strong> can generate an outgoing email.</p>

            <div style="margin: 24px 0; padding: 20px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0;">
                <p style="margin: 0 0 10px;"><strong>Mailer:</strong> {{ $activeMailer }}</p>
                <p style="margin: 0 0 10px;"><strong>Triggered by:</strong> {{ $sentBy }}</p>
                <p style="margin: 0 0 10px;"><strong>Sent at:</strong> {{ now()->format('F j, Y g:i A') }}</p>
                <p style="margin: 0;"><strong>From:</strong> {{ $fromName ?: $hotelName }}{{ $fromAddress ? ' <' . $fromAddress . '>' : '' }}</p>
            </div>

            <p style="margin-bottom: 0; color: #475569;">If you are using a sandbox mailer such as <strong>log</strong> or <strong>array</strong>, this confirms application-level mail generation rather than external inbox delivery.</p>
        </div>
    </div>
</body>
</html>
