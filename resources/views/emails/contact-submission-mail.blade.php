<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>New Contact Message</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #0f172a; background: #f8fafc; margin: 0; padding: 24px;">
    <div style="max-width: 640px; margin: 0 auto; background: #ffffff; border: 1px solid #e2e8f0; border-radius: 18px; overflow: hidden;">
        <div style="background: linear-gradient(135deg, #0f172a 0%, #4f46e5 100%); color: #ffffff; padding: 28px 32px;">
            <p style="margin: 0 0 8px; font-size: 12px; letter-spacing: 0.16em; text-transform: uppercase; opacity: 0.8;">Contact Form</p>
            <h1 style="margin: 0; font-size: 28px; line-height: 1.2;">New message for {{ $hotelName }}</h1>
        </div>

        <div style="padding: 32px;">
            <div style="margin: 0 0 24px; padding: 20px; border-radius: 14px; background: #f8fafc; border: 1px solid #e2e8f0;">
                <p style="margin: 0 0 10px;"><strong>From:</strong> {{ $name }} &lt;{{ $email }}&gt;</p>
                <p style="margin: 0;"><strong>Subject:</strong> {{ $subject }}</p>
            </div>

            <div style="margin: 0 0 24px; padding: 20px 0;">
                <h2 style="margin: 0 0 8px; font-size: 16px;">Message</h2>
                <p style="margin: 0; white-space: pre-wrap; color: #334155;">{{ $message }}</p>
            </div>

            <p style="margin: 0; color: #475569; font-size: 13px;">This message was submitted through the contact form on the {{ $hotelName }} website.</p>
        </div>
    </div>
</body>
</html>
