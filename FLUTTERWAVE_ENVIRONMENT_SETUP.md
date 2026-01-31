# Flutterwave Environment Configuration Guide

## ⚙️ Quick Setup

### 1. Get Flutterwave API Keys

1. **Sign up at Flutterwave**: https://dashboard.flutterwave.com/signup
2. **Log in to Dashboard**: https://dashboard.flutterwave.com
3. **Go to Settings → API**: Copy these keys:
   - **Public Key** (starts with `pk_`)
   - **Secret Key** (starts with `sk_`)

### 2. Get Webhook Secret Hash

1. **In Flutterwave Dashboard**: Go to Settings → Webhooks
2. **View Webhook Event Signature**: Copy the "Webhook Secret Hash"
3. This is used to verify webhook authenticity

### 3. Update Environment File

Edit your `.env` file (or `.env.production` for production):

```env
# Flutterwave Payment Gateway
FLUTTERWAVE_ENVIRONMENT=sandbox        # or 'production'
FLUTTERWAVE_PUBLIC_KEY=pk_your_key_here
FLUTTERWAVE_SECRET_KEY=sk_your_key_here
FLUTTERWAVE_SECRET_HASH=your_webhook_hash_here

# Optional: Payment Points Loyalty (if enabled)
FLUTTERWAVE_ENABLE_PAYMENT_POINTS=true
```

### 4. Configure Webhooks in Flutterwave Dashboard

1. **Go to Settings → Webhooks** in Flutterwave Dashboard
2. **Click "Add Webhook Endpoint"**
3. **URL**: `https://yourhotel.com/webhooks/flutterwave`
4. **HTTP Method**: POST
5. **Select Events**:
   - ✅ charge.completed
   - ✅ charge.failed
   - ✅ charge.reversed
   - ✅ transfer.completed
   - ✅ transfer.failed
   - ✅ refund.completed
   - ✅ refund.failed
6. **Click Save**

### 5. Test Configuration

```bash
# Clear config cache and reload
php artisan config:clear
php artisan config:cache

# Test Flutterwave API connection
php artisan tinker
>>> $service = app(App\Services\FlutterwaveService::class);
>>> $service->testConnection();
// Should return connection status
```

### 6. Enable Payment Methods in Application

The following payment methods are now available:

#### Event Tickets
- **Prepaid Only** → Redirects to Flutterwave checkout
- No offline option available

#### Room Bookings
- **Offline** (Default) → Pay at checkout during check-in
- **Online** → Pay now via Flutterwave (prepaid)
- Guest selects preference when creating booking

#### Room Service Orders
- **Postpaid** (Default) → Pay after delivery
- **Online** → Pay now via Flutterwave (prepaid)
- Guest selects preference when placing order

---

## 🔄 Webhook Configuration Details

### Webhook Verification

Flutterwave sends a `verif-hash` header with each webhook. The system automatically verifies this:

```php
// Verification logic (automatic)
$hash = hash_hmac('sha512', json_encode($payload), FLUTTERWAVE_SECRET_HASH);
if ($hash !== $request->header('verif-hash')) {
    return 401; // Rejected
}
```

### Webhook Retry Policy

If your webhook endpoint returns:
- **200-299**: Considered successful, no retry
- **Other codes**: Flutterwave will retry up to 5 times

**Best Practices**:
- Return 200 immediately after validation
- Process webhook asynchronously (use jobs)
- Log all webhook events for debugging

### Sample Webhook Event

```json
{
  "event": "charge.completed",
  "data": {
    "id": 12345678,
    "tx_ref": "ORD-12345",
    "flw_ref": "FLW123456789",
    "device_fingerprint": "...",
    "amount": 5000,
    "currency": "NGN",
    "charged_amount": 5000,
    "app_fee": 125,
    "merchant_fee": 0,
    "processor_response": "Approved",
    "auth_model": "NOAUTH",
    "ip": "192.168.1.1",
    "narration": "Room Service Order",
    "status": "successful",
    "payment_type": "card",
    "created_at": "2025-02-01T10:30:00.000Z",
    "account_id": 123456,
    "customer": {
      "id": 456789,
      "name": "John Doe",
      "email": "john@example.com",
      "phone_number": "+234XXXXXXXXXX"
    },
    "card": {
      "card_type": "VISA",
      "last_4chars": "1234"
    }
  }
}
```

---

## 🧪 Testing in Sandbox Mode

### 1. Use Sandbox API Keys

For testing, use sandbox credentials:
- **Sandbox Public Key**: pk_test_xxxxx
- **Sandbox Secret Key**: sk_test_xxxxx
- **Sandbox Webhook Hash**: test_hash_xxxxx

### 2. Test Cards

Use these card numbers for sandbox testing:

```
// Successful Payment
Card: 5531 8866 5214 2950
CVV: 564
Exp: 09/32
PIN: 1234

// Failed Payment
Card: 5531 8866 5214 2950
CVV: 123
Exp: 09/32
PIN: 0000

// 3D Secure (OTP Required)
Card: 5500 0000 0000 0004
CVV: 564
Exp: 09/32
PIN: 1234
OTP: 12345
```

### 3. Test Webhook Manually

In Flutterwave Dashboard:
1. Go to Webhooks
2. Find your endpoint
3. Click "Test Event"
4. Select event type (e.g., charge.completed)
5. Click "Send" 
6. Check your application logs

### 4. Verify Webhook Receipt

```bash
# Check Laravel logs
tail -f storage/logs/laravel.log | grep webhook

# Or check database
SELECT * FROM payments WHERE created_at > NOW() - INTERVAL 5 MINUTE;
```

---

## 📊 Monitoring & Troubleshooting

### Check Webhook Status

```bash
# In Laravel Tinker
php artisan tinker
>>> DB::table('payments')->latest()->first();
// Review the payment record
>>> DB::table('journal_entries')->latest()->take(5)->get();
// Verify accounting entries created
```

### Common Configuration Issues

#### Issue: "Invalid API Key"
```
Error: Authentication failed with Flutterwave
```
**Solution**: 
- Verify API keys are correct in .env
- Ensure environment matches (sandbox vs production)
- Run `php artisan config:cache`

#### Issue: "Webhook Signature Invalid"
```
Error: Unauthorized webhook request
```
**Solution**:
- Verify webhook secret hash is correct
- Check webhook URL is exact match in Flutterwave dashboard
- Ensure .env is reloaded after changes

#### Issue: "No Webhook Events Received"
**Solution**:
1. Verify webhook URL is publicly accessible
2. Check domain DNS is resolving correctly
3. Ensure firewall allows POST requests
4. Test webhook from Flutterwave dashboard

---

## 🚀 Production Deployment

### Pre-Deployment Checklist

- [ ] Switch from sandbox to production API keys
- [ ] Update FLUTTERWAVE_ENVIRONMENT=production
- [ ] Verify webhook URL (https, not http)
- [ ] Configure webhook events in production dashboard
- [ ] Enable HTTPS on your domain
- [ ] Set SSL certificate (required for webhooks)
- [ ] Test payment flow end-to-end in production
- [ ] Monitor first 24-48 hours of payments

### Deployment Steps

```bash
# 1. Update environment file
nano .env.production
# Set FLUTTERWAVE_ENVIRONMENT=production
# Update all FLUTTERWAVE_ keys

# 2. Clear cache
php artisan config:clear

# 3. Update configuration
php artisan config:cache

# 4. Restart application
php artisan queue:restart

# 5. Monitor logs
php artisan pail
```

### Post-Deployment

1. **Test a real payment** (small amount)
2. **Verify webhook received** in logs
3. **Check accounting entry** was created
4. **Monitor for 24 hours** for any issues
5. **Review transaction reports** in Flutterwave dashboard

---

## 💡 Pro Tips

### Payment References

The system uses structured references for tracking:
```
Event Tickets: EVT-{ticket_id}-{random}
Bookings:      BKG-{booking_id}-{random}
Orders:        ORD-{order_id}-{random}
```

This allows easy lookup of payments in both systems.

### Idempotency

The system handles duplicate webhooks gracefully:
- Payment reference must be unique
- Status checks before processing
- Prevents double-charging

### Accounting Integration

Every successful payment automatically creates:
1. **Revenue Entry**: Customer payment received
2. **Bank Entry**: Amount deposited to bank
3. **Fee Entry**: Transaction fees deducted

Access these in: Admin → Accounting → Journal Entries

---

## 📞 Need Help?

### Resources
- **Flutterwave Docs**: https://developer.flutterwave.com
- **API Reference**: https://docs.flutterwave.com/api/
- **Support Email**: support@flutterwave.com
- **Chat Support**: Available in Flutterwave Dashboard

### Application Support
- Check [FLUTTERWAVE_INTEGRATION.md](FLUTTERWAVE_INTEGRATION.md) for detailed docs
- Review test files in `tests/Feature/Payments/` for usage examples
- Check application logs: `storage/logs/laravel.log`

---

**Last Updated**: January 31, 2026
**Version**: 1.0 (Production Ready)
