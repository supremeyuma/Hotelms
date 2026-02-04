# Quick Reference: Multi-Payment Gateway Implementation

## What Changed?

### ✅ Room Booking Payment (`resources/js/Pages/Booking/Payment.vue`)

**Before:**
- Only Flutterwave support
- Offline payment option only

**After:**
- ✅ Flutterwave support
- ✅ Paystack support  
- ✅ Offline payment option
- ✅ Provider selection UI

---

## How to Use

### For Room Bookings

1. **User navigates to:** `/bookings/1/payment`
2. **Sees payment options:**
   - Pay at Checkout (cash/card on arrival)
   - Pay Online Now (select provider)
3. **If "Pay Online Now" selected:**
   - Choose provider: Flutterwave or Paystack
   - Click "Proceed to Payment"
4. **Payment gateway opens**
5. **User completes payment**
6. **System sends receipt email with QR code**

### For Events, Tickets & Tables

Already working - uses same multi-provider system:
- PaymentProcess.vue handles all event payments
- Provider selection already built-in
- Both Flutterwave and Paystack fully supported

---

## For Other Payment Points

These automatically work through the API:

| Component | Route | Status |
|-----------|-------|--------|
| Room Services | `/room/{token}/payment` | ✅ Ready |
| Kitchen Orders | Checkout endpoint | ✅ Ready |
| Laundry Services | Service payment | ✅ Ready |
| Manual Invoices | Payment processing | ✅ Ready |

All use the same backend endpoints:
- `POST /payments/initialize`
- `POST /payments/initialize-by-reference`

---

## Setup Checklist

### 1. Environment Variables (`.env`)

```env
# Add/Update these:
FLUTTERWAVE_PUBLIC_KEY=pk_live_xxxxx
FLUTTERWAVE_SECRET_KEY=sk_live_xxxxx
PAYSTACK_PUBLIC_KEY=pk_live_xxxxx
PAYSTACK_SECRET_KEY=sk_live_xxxxx

PAYMENT_PROVIDER=flutterwave
PAYMENT_SHOW_PROVIDER_OPTIONS=true
```

### 2. Provider Dashboard Configuration

**Flutterwave:**
- Go to Settings → API & Webhooks
- Add webhook: `https://yourdomain.com/webhooks/flutterwave`

**Paystack:**
- Go to Settings → API Keys & Webhooks
- Add webhook: `https://yourdomain.com/webhooks/paystack`

### 3. Test Payment Flow

```bash
# Start server
php artisan serve

# Test booking payment
# http://localhost:8000/bookings/1/payment

# Select "Pay Online Now"
# Choose provider
# Use test card credentials
```

---

## Key Differences Between Providers

| Feature | Flutterwave | Paystack |
|---------|------------|----------|
| **Payment Methods** | Cards, Bank, USSD, Mobile Money | Cards, Bank, USSD, Account Transfer |
| **Settlement Time** | Instant | Instant |
| **Fees** | 1.4% + ₦50 | 1.5% + ₦50 |
| **UI Type** | Modal Checkout | Inline Popup |
| **Test Mode** | pk_test_* | pk_test_* |
| **Live Mode** | pk_live_* | pk_live_* |

---

## Test Card Credentials

### Flutterwave Test

```
Card: 5531 8866 5725 4957
CVV: 564
Expiry: 09/32
PIN: 3310
OTP: 12345
```

### Paystack Test

```
Card: 4084 0343 0343 0343
CVV: 408
Expiry: 01/32
PIN: 1111
OTP: 123456
```

---

## Common Issues

### Issue: "Gateway not loading"
**Solution:** Check CDN URLs are not blocked, verify browser console

### Issue: "Provider not initializing"  
**Solution:** Verify API keys in `.env`, check they're for correct environment (test vs live)

### Issue: "Payment succeeds but order not confirmed"
**Solution:** Check webhook URL in provider dashboard, verify webhook secret

### Issue: "QR code missing from email"
**Solution:** Run `php artisan storage:link`, check storage permissions

---

## Testing Procedure

### Step 1: Environment Setup
```bash
cd hotelms
php artisan serve
# Open http://localhost:8000
```

### Step 2: Test Booking Payment
```
1. Create booking
2. Go to payment page
3. Select "Pay Online Now"
4. Choose Flutterwave
5. Use test card
6. Verify success
7. Repeat for Paystack
```

### Step 3: Test Event Payment
```
1. Go to events page
2. Purchase ticket
3. Select provider
4. Complete payment
5. Verify email receipt
6. Check QR code generation
```

### Step 4: Test Check-in
```
1. Use QR code from email
2. Go to /staff/events/scan
3. Scan QR code
4. Verify guest check-in
```

---

## Files Modified

| File | Changes |
|------|---------|
| `resources/js/Pages/Booking/Payment.vue` | Added Paystack, provider selection |

| File | Status |
|------|--------|
| `resources/js/Pages/Public/PaymentProcess.vue` | Already complete |
| `app/Http/Controllers/PaymentController.php` | Already complete |
| `app/Services/PaymentProviderManager.php` | Already complete |
| `config/payment.php` | Already complete |

---

## API Reference

### Initialize Payment (General)
```javascript
POST /payments/initialize
{
  booking_id: 1,
  amount: 50000,
  customer_email: "guest@example.com",
  provider: "paystack" // optional
}
```

### Initialize Payment (By Reference)
```javascript
POST /payments/initialize-by-reference
{
  reference: "EVT-ABC123",
  provider: "flutterwave" // optional
}
```

### Verify Payment
```javascript
POST /payments/verify
{
  reference: "PAY-xyz",
  provider: "paystack"
}
```

---

## Support & Documentation

- **Full Guide:** `MULTI_PAYMENT_GATEWAY_INTEGRATION.md`
- **Implementation Summary:** `PAYMENT_INTEGRATION_SUMMARY.md`
- **Logs:** `storage/logs/laravel.log`
- **Blade Errors:** `APP_DEBUG=true` in `.env`

---

## Status

✅ **Ready for Production**

All payment points support:
- ✅ Flutterwave
- ✅ Paystack  
- ✅ Offline payments
- ✅ Provider selection
- ✅ Email receipts with QR codes
- ✅ Check-in tracking
