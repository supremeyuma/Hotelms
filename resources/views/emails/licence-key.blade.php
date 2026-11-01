<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Your MooreLife licence</title>
</head>
<body style="font-family: Arial, sans-serif; line-height: 1.6; color: #334155; max-width: 640px; margin: 0 auto; padding: 24px; background-color: #f8fafc;">
    <div style="background-color: #ffffff; border: 1px solid #e2e8f0; border-radius: 24px; overflow: hidden;">
        <div style="background-color: #0f172a; padding: 28px 32px; color: #ffffff;">
            <p style="margin: 0 0 8px; font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Payment received</p>
            <h1 style="margin: 0; font-size: 30px; line-height: 1.2;">Your licence key is ready</h1>
            <p style="margin: 12px 0 0; font-size: 15px; color: #cbd5e1;">{{ $order->property_name }}</p>
        </div>

        <div style="padding: 32px;">
            <p style="margin-top: 0; font-size: 16px;">Hello {{ $order->customer_name ?: 'there' }},</p>
            <p style="margin-bottom: 24px; color: #475569;">
                Thank you for your payment. This key activates MooreLife for
                <strong>{{ $order->property_name }}</strong> and covers the server plus every
                terminal and staff client that pairs with it.
            </p>

            <div style="background-color: #0f172a; border-radius: 18px; padding: 24px; margin-bottom: 24px; text-align: center;">
                <p style="margin: 0 0 10px; font-size: 11px; letter-spacing: 0.2em; text-transform: uppercase; color: #94a3b8; font-weight: 700;">Licence key</p>
                <p style="margin: 0; font-family: 'Courier New', monospace; font-size: 24px; font-weight: 700; letter-spacing: 0.12em; color: #ffffff;">{{ $licenceKey }}</p>
            </div>

            <div style="background-color: #f8fafc; border: 1px solid #e2e8f0; border-radius: 18px; padding: 24px; margin-bottom: 24px;">
                <h2 style="margin: 0 0 16px; font-size: 18px; color: #0f172a;">What you bought</h2>
                <p style="margin: 0 0 10px;"><strong>Property:</strong> {{ $order->property_name }} ({{ $order->rooms }} rooms)</p>
                <p style="margin: 0 0 10px;"><strong>Plan:</strong> {{ $licence->licencePlan?->name ?? 'Annual licence' }}</p>
                <p style="margin: 0 0 10px;"><strong>Term:</strong> {{ $order->term_months }} months from activation</p>
                <p style="margin: 0 0 10px;"><strong>Paid:</strong> {{ $order->currency }} {{ number_format((float) $order->amount, 2) }}</p>
                <p style="margin: 0;"><strong>Reference:</strong> {{ $order->reference }}</p>
            </div>

            <h2 style="margin: 0 0 12px; font-size: 18px; color: #0f172a;">How to activate</h2>
            <ol style="margin: 0 0 24px; padding-left: 20px; color: #475569;">
                <li style="margin-bottom: 8px;">Open MooreLife on the computer that runs your server.</li>
                <li style="margin-bottom: 8px;">Go to <strong>Activate licence</strong> (on the trial banner, or on the screen shown when the trial ends).</li>
                <li style="margin-bottom: 8px;">Paste the key above and confirm.</li>
            </ol>
            <p style="margin: 0 0 24px; color: #475569;">
                Activation needs an internet connection once. After that the app verifies your
                licence offline. The term starts when you activate, so the key can wait until
                you are ready to install.
            </p>

            <p style="margin: 0; font-size: 13px; color: #64748b;">
                Keep this email. The key is not stored in a recoverable form on our side, so this
                message is your copy. If you lose it, contact support and we will issue a
                replacement.
            </p>
        </div>
    </div>
</body>
</html>
