<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $hotelName ?? 'Reservations' }} &middot; Arrival at the front desk</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=figtree:400,500,600&display=swap" rel="stylesheet">
    <style>
        :root {
            --ink: #16202b;
            --muted: #5b6b7b;
            --line: #e2e8f0;
            --accent: #0f766e;
            --accent-dark: #115e59;
            --bg: #f6f8fa;
        }
        * { box-sizing: border-box; }
        body {
            margin: 0;
            min-height: 100vh;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 24px;
            background: var(--bg);
            font-family: Figtree, system-ui, -apple-system, Segoe UI, Roboto, sans-serif;
            color: var(--ink);
        }
        .card {
            width: 100%;
            max-width: 560px;
            background: #fff;
            border: 1px solid var(--line);
            border-radius: 16px;
            padding: 40px;
            box-shadow: 0 18px 40px -28px rgba(15, 23, 42, .35);
        }
        .brand {
            font-size: 13px;
            letter-spacing: .14em;
            text-transform: uppercase;
            color: var(--accent);
            font-weight: 600;
            margin: 0 0 18px;
        }
        h1 { font-size: 24px; line-height: 1.3; margin: 0 0 12px; }
        p { color: var(--muted); line-height: 1.6; margin: 0 0 16px; }
        .facts {
            border: 1px solid var(--line);
            border-radius: 12px;
            padding: 16px 18px;
            margin: 22px 0;
            background: #fbfcfd;
        }
        .facts dl { margin: 0; display: grid; grid-template-columns: auto 1fr; gap: 8px 16px; }
        .facts dt { color: var(--muted); font-size: 13px; }
        .facts dd { margin: 0; font-weight: 600; text-align: right; }
        .actions { display: flex; gap: 12px; flex-wrap: wrap; margin-top: 24px; }
        a.button {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            padding: 12px 20px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 15px;
            text-decoration: none;
            background: var(--accent);
            color: #fff;
        }
        a.button:hover { background: var(--accent-dark); }
        a.button.secondary { background: #fff; color: var(--ink); border: 1px solid var(--line); }
        a.button.secondary:hover { background: #f1f5f9; }
        .foot { margin-top: 26px; font-size: 13px; color: var(--muted); }
    </style>
</head>
<body>
    <main class="card">
        <p class="brand">{{ $hotelName ?? 'Reservations' }}</p>
        <h1>Please book this arrival at the front desk</h1>
        <p>
            Arrivals within {{ $cutoffHours }} hours are arranged directly with the front desk.
            We can't take this stay online, but the desk will look after it.
        </p>

        <div class="facts">
            <dl>
                <dt>Arrival</dt>
                <dd>{{ $checkIn }}</dd>
                <dt>Online bookings open from</dt>
                <dd>{{ $windowDescription }}</dd>
            </dl>
        </div>

        <p>Call or message the front desk and we&rsquo;ll reserve it for you.</p>

        <div class="actions">
            @if (! empty($hotelPhone))
                <a class="button" href="tel:{{ preg_replace('/\s+/', '', $hotelPhone) }}">Call {{ $hotelPhone }}</a>
            @endif
            @if (! empty($whatsapp))
                <a class="button secondary" href="https://wa.me/{{ preg_replace('/\D+/', '', $whatsapp) }}">WhatsApp {{ $whatsapp }}</a>
            @endif
            <a class="button secondary" href="{{ route('booking.search') }}">Change dates</a>
        </div>

        <p class="foot">
            For a later stay, our online booking is open any time: book from
            {{ $windowDescription }} onward.
        </p>
    </main>
</body>
</html>
