# Flutterwave Payment Integration Guide

## Overview

The HotelMS system integrates **Flutterwave** as the primary online payment gateway for:
- **Event Tickets** (prepaid only)
- **Table Reservations** (prepaid only)
- **Room Bookings** (prepaid or pay-at-checkout)
- **Room Service Orders** (prepaid or postpaid)

All online payments are automatically wired to the accounting system and create ledger entries.

---

## Environment Variables

Add the following to your `.env` file:

```bash
FLUTTERWAVE_PUBLIC_KEY=your_flutterwave_public_key
FLUTTERWAVE_SECRET_KEY=your_flutterwave_secret_key
FLUTTERWAVE_SECRET_HASH=your_webhook_secret_hash
```

### Getting Your Keys

1. Log in to [Flutterwave Dashboard](https://dashboard.flutterwave.com)
2. Navigate to **Settings → API Keys**
3. Copy your **Public Key** and **Secret Key**
4. For webhooks, go to **Settings → Webhooks** and set the secret hash

---

## Webhook Configuration

### Webhook URL

Configure this URL in your Flutterwave dashboard:

```
https://your-hotel-domain.com/webhooks/flutterwave
```

### Webhook Secret Hash

1. In Flutterwave dashboard, go to **Settings → Webhooks**
2. Copy the **Secret Hash**
3. Add it to your `.env` file as `FLUTTERWAVE_SECRET_HASH`

### Webhook Events Handled

- `charge.completed` → Payment successful (updates accounting ledger, confirms bookings/tickets)
- `charge.failed` → Payment failed (marks payment as failed)
- `transfer.completed` → Transfer success (logged)
- `transfer.failed` → Transfer failed (logged)
- `refund.completed` → Refund processed (updates payment & accounting)
- `refund.failed` → Refund failed (logged)

---

## Payment Flows

### 1. Event Tickets (Prepaid Only)

**Flow:**
1. Guest selects ticket type and quantity on `/events/{event}/tickets`
2. Chooses "Pay Online Now" (only option for prepaid events)
3. System calls `/payments/initialize` → returns Flutterwave checkout data
4. Flutterwave inline checkout appears
5. On success, redirects to `/events/payment/callback?tx_ref=...&status=success`
6. Server confirms payment via webhook & updates ticket status to `confirmed`

**Files Involved:**
- Frontend: `resources/js/Pages/Public/PaymentProcess.vue`
- Backend: `PublicEventController@paymentCallback()`, `FlutterwaveService@handleSuccessfulPayment()`

### 2. Room Bookings (Prepaid or Pay-at-Checkout)

**Flow:**
1. Guest completes booking form and reaches `/booking/payment/{booking}`
2. Selects payment method:
   - **"Pay at Checkout"** (offline) → Confirms booking, due at check-in
   - **"Pay Online Now"** → Redirects to Flutterwave checkout
3. On online payment success:
   - Webhook updates `Payment::status = 'paid'`
   - `PaymentAccountingService::handleSuccessful()` posts entries to ledger
   - Booking is confirmed

**Files Involved:**
- Frontend: `resources/js/Pages/Booking/Payment.vue`
- Backend: `BookingController@paymentCallback()`, `PaymentController@initialize()`

### 3. Room Service Orders (Postpaid or Online Prepay)

**Flow:**
1. Guest places order via room service menu (TV or app)
2. Order created with `payment_method` (offline/online) and `payment_status`
3. If `payment_method = 'online'`:
   - Order sent for payment confirmation before processing
   - Guest completes Flutterwave checkout
   - Staff receives order once payment confirmed
4. If `payment_method = 'postpaid'`:
   - Order processed immediately
   - Payment collected at departure or via final bill

**Files Involved:**
- Backend: `OrderController@store()`, `OrderController@paymentCallback()`, `FlutterwaveService`

---

## API Endpoints

### Payment Initialization

**POST** `/payments/initialize`

Request:
```json
{
  "amount": 50000,
  "currency": "NGN",
  "tx_ref": "BK-123",
  "description": "Booking #123",
  "booking_id": 123,
  "customer_email": "guest@example.com",
  "customer_name": "John Doe"
}
```

Response:
```json
{
  "public_key": "pk_test_...",
  "tx_ref": "BK-123",
  "amount": 50000,
  "currency": "NGN",
  "customer": {
    "email": "guest@example.com",
    "name": "John Doe"
  }
}
```

### Payment Verification

**GET** `/payments/verify/{reference}`

Verifies a payment with Flutterwave API and updates local records.

### Webhook Handler

**POST** `/webhooks/flutterwave`

Requires header: `verif-hash` (Flutterwave signature)

Processes Flutterwave events and updates:
- `Payment` status
- Event ticket / table reservation status
- Accounting ledger entries

---

## Accounting Integration

All successful online payments automatically create accounting ledger entries:

**Entry Type:** Payment Received
- **Debit:** Bank/Payment Account (e.g., "Flutterwave - Mobile Money")
- **Credit:** Revenue Account (e.g., "Room Sales", "Event Sales")

**Triggered By:**
1. Webhook: `charge.completed` event → `FlutterwaveService@handleSuccessfulPayment()`
2. Server verification: `PaymentController@verify()` → `PaymentAccountingService@handleSuccessful()`

**Service Class:** `App\Services\PaymentAccountingService`

---

## Error Handling

### Common Issues

| Issue | Solution |
|-------|----------|
| "Signature verification failed" | Verify `FLUTTERWAVE_SECRET_HASH` is correct in dashboard |
| "Payment not found in system" | Ensure payment initialized before checkout |
| "Webhook not received" | Check webhook URL is publicly accessible (not localhost) |
| "Accounting entry failed" | Verify accounting accounts are configured (see `TaxService`) |

### Logging

Check logs for payment failures:
```bash
tail -f storage/logs/laravel.log
```

Search for:
- `'Flutterwave payment'` → Payment events
- `'Payment accounting failed'` → Accounting errors
- `'Webhook processing failed'` → Webhook errors

---

## Testing

### Development/Sandbox Mode

1. Use **Flutterwave Test Keys** from dashboard
2. Test Flutterwave provides test card numbers:
   - **Visa:** `4242 4242 4242 4242` (any future expiry, any CVV)
   - **Mastercard:** `5531 8866 5522 2950`

### Testing Workflows

**Event Ticket:**
```
1. Navigate to /events/{event}/tickets
2. Select quantity, click "Pay Online Now"
3. Use test card 4242 4242 4242 4242
4. Verify ticket status changes to "confirmed"
```

**Booking:**
```
1. Complete booking form
2. At payment page, select "Pay Online Now"
3. Use test card
4. Verify booking confirmed
```

**Webhook Testing:**
- Use Flutterwave dashboard to trigger test webhook events
- Or use curl:
```bash
curl -X POST http://localhost:8000/webhooks/flutterwave \
  -H "verif-hash: test_hash" \
  -H "Content-Type: application/json" \
  -d '{"event":"charge.completed","data":{"id":1,"tx_ref":"BK-123","status":"successful","amount":50000}}'
```

---

## Monitoring

### Key Metrics to Monitor

1. **Payment Success Rate:** `Payment::where('status','successful')->count()`
2. **Failed Payments:** `Payment::where('status','failed')->count()`
3. **Accounting Reconciliation:** Verify ledger debits = credits
4. **Webhook Failures:** Search logs for `"Webhook processing failed"`

### Scheduled Tasks

The system includes scheduled jobs to clean up stale payments:
```
app/Jobs/CleanupAuditLogsJob.php → Runs daily
```

---

## Support

For Flutterwave API issues, see:
- [Flutterwave API Docs](https://developer.flutterwave.com/docs)
- [Flutterwave Support](https://support.flutterwave.com)

For HotelMS-specific issues:
- Check `config/flutterwave.php`
- Review `app/Services/FlutterwaveService.php`
- Check webhook logs in `storage/logs/laravel.log`

---

## Configuration Files

### `config/flutterwave.php`
```php
return [
    'public_key' => env('FLUTTERWAVE_PUBLIC_KEY'),
    'secret_key' => env('FLUTTERWAVE_SECRET_KEY'),
    'secret_hash' => env('FLUTTERWAVE_SECRET_HASH'),
];
```

### Environment Example (`.env`)
```
FLUTTERWAVE_PUBLIC_KEY=pk_live_abc123def456
FLUTTERWAVE_SECRET_KEY=sk_live_xyz789uvw012
FLUTTERWAVE_SECRET_HASH=whsk_live_tst123
```

---

**Last Updated:** January 31, 2026  
**Version:** 1.0
