# 🧪 Payment System Testing Guide

## Quick Test Instructions

### Step 1: Verify Configuration
```bash
# Clear cache to reload configuration
php artisan config:clear
php artisan cache:clear
```

### Step 2: Start Your App
```bash
composer run dev
# This starts Laravel on http://localhost:8000
```

### Step 3: Navigate to Payment
1. Go to your hotel booking/event page
2. Select a ticket, table, or booking
3. Proceed to payment

### Step 4: Verify Provider Options Display

**You should see**:
- Amount to pay
- **"Choose Payment Method" section** with two buttons:
  - `Flutterwave`
  - `Paystack`

If you DON'T see provider options:
```bash
# Hard refresh browser: Ctrl+Shift+R (Windows/Linux) or Cmd+Shift+R (Mac)

# Clear browser storage:
# 1. Open DevTools (F12)
# 2. Go to Storage/Application tab
# 3. Click "Clear Site Data"
# 4. Refresh page
```

### Step 5: Test Flutterwave

1. Click **Flutterwave** button
2. Click **"Pay Now"**
3. Flutterwave checkout should appear
4. Use test card:
   - Number: `4242 4242 4242 4242`
   - Expiry: `09/25`
   - CVV: `123`
   - OTP: `123456` (if prompted)
5. Verify success page appears

### Step 6: Test Paystack

1. Go back to payment page
2. Click **Paystack** button
3. Click **"Pay Now"**
4. Paystack checkout should appear
5. Use test card:
   - Number: `4111 1111 1111 1111`
   - Expiry: `12/25`
   - CVV: `408`
6. Verify success page appears

---

## Debugging

### Check Browser Console
Open DevTools (F12) and go to Console tab to see any JavaScript errors.

**Look for**:
- ✅ No 404 errors
- ✅ Payment initialization logs
- ✅ "Flutterwave loaded" or "Paystack loaded"

### Check Laravel Logs
```bash
# Stream logs in real-time
php artisan pail
```

**Look for**:
- Payment initialization events
- Provider selection logs
- No error messages

### Verify Payment in Database
```bash
php artisan tinker

# Check payments table
Payment::latest()->first()

# Should show:
# - provider: 'flutterwave' or 'paystack'
# - reference: transaction reference
# - amount: payment amount
# - status: pending, completed, etc.
```

### Check Configuration
```bash
php artisan tinker

# Verify both providers are enabled
config('payment.providers')
// Should output: array:2 ["flutterwave" => true, "paystack" => true]

# Check payment manager
app('App\Services\PaymentProviderManager')->getEnabledProviders()
// Should output: array:2 [0 => "flutterwave", 1 => "paystack"]
```

---

## Common Issues & Solutions

### Issue 1: Missing `tx_ref` Error from Flutterwave

**Error Message**:
```
Missing parameter (`txref`): Kindly terminate this session and reconfirm the data.
```

**Solution**:
✅ This should be FIXED now. If you still see it:
1. Clear cache: `php artisan config:clear`
2. Hard refresh browser: `Ctrl+Shift+R`
3. Check that `PaymentController.buildPaymentResponse()` includes `'tx_ref' => $reference`

### Issue 2: No Provider Options Showing

**What You See**: Only Flutterwave option, no choice for Paystack

**Solution**:
1. Verify both are enabled in `.env`:
   ```
   PAYMENT_FLUTTERWAVE_ENABLED=true
   PAYMENT_PAYSTACK_ENABLED=true
   ```

2. Clear cache:
   ```bash
   php artisan config:clear && php artisan cache:clear
   ```

3. Hard refresh browser: `Ctrl+Shift+R`

### Issue 3: Paystack SDK Not Loading

**Error**: "Cannot read property 'setup' of undefined"

**Solution**:
1. Check browser console for failed script load
2. Verify Paystack CDN is accessible: `https://js.paystack.co/v1/inline.js`
3. Check network tab in DevTools for 404s on Paystack script

### Issue 4: Webhook Not Received

**Symptom**: Payment completed but guest shows as unpaid

**Solutions**:
1. Verify webhook URL in provider dashboards:
   - **Flutterwave**: `https://yourdomain.com/webhooks/flutterwave`
   - **Paystack**: `https://yourdomain.com/webhooks/paystack`

2. Test webhook manually:
   ```bash
   # Flutterwave webhook test
   curl -X POST https://yourdomain.com/webhooks/flutterwave \
     -H "verif-hash: test_signature" \
     -d '{"event":"charge.completed"}'

   # Should return 200 OK
   ```

3. Check logs:
   ```bash
   php artisan pail | grep webhook
   ```

---

## What Each File Does

### Frontend (Vue)
- **`resources/js/Pages/Public/PaymentProcess.vue`**
  - Displays payment UI
  - Shows provider selection (when enabled)
  - Loads appropriate payment SDK
  - Handles provider-specific checkout

### Backend (Laravel)
- **`app/Http/Controllers/PaymentController.php`**
  - Initializes payments
  - Validates requests
  - Returns response with provider info and `tx_ref`
  - Verifies payments

- **`app/Services/PaymentProviderManager.php`**
  - Determines enabled providers
  - Decides if guest sees options
  - Selects appropriate provider service

- **`app/Services/PaystackService.php`**
  - Handles Paystack API calls
  - Initializes payments with Paystack
  - Verifies Paystack transactions

- **`app/Services/FlutterwaveService.php`**
  - Handles Flutterwave API calls
  - Initializes payments with Flutterwave
  - Verifies Flutterwave transactions

- **`app/Http/Controllers/WebhookController.php`**
  - Receives webhooks from both providers
  - Verifies signatures
  - Confirms payments

### Configuration
- **`config/payment.php`**
  - Payment provider settings
  - API credentials
  - Webhook configuration

- **`.env`**
  - Environment-specific provider settings
  - API keys
  - Enable/disable providers

---

## Test Scenarios

### Scenario 1: Both Providers Enabled
```
Provider Selection: YES ✓
Guest sees both Flutterwave and Paystack options
Guest can choose which to use
```

### Scenario 2: Only Flutterwave Enabled
```
Provider Selection: NO
Auto-selects Flutterwave (no choice shown)
Flutterwave checkout loads automatically
```

### Scenario 3: Only Paystack Enabled
```
Provider Selection: NO
Auto-selects Paystack (no choice shown)
Paystack checkout loads automatically
```

### Scenario 4: Payment Verification
```
1. Complete payment on Flutterwave
2. Webhook sent to /webhooks/flutterwave
3. Backend verifies signature
4. Payment marked as confirmed
5. Guest redirected to success page
```

---

## Performance Checklist

- ✅ Payment initialization < 1 second
- ✅ SDK loads < 2 seconds
- ✅ Checkout opens < 1 second
- ✅ Webhook processed < 500ms
- ✅ No memory leaks (check browser DevTools)

---

## Security Checklist

- ✅ All API keys in `.env` (never hardcoded)
- ✅ Webhook signatures verified
- ✅ HTTPS only in production
- ✅ CORS properly configured
- ✅ SQL injection prevention (Laravel built-in)

---

## Success Criteria

✅ Test PASSED when:
1. Provider selection shows when both enabled
2. Can click either provider button
3. Correct checkout loads for selected provider
4. Can complete payment with test card
5. Success page appears
6. Payment recorded in database
7. Payment shows correct provider name
8. Webhook webhook confirmation received
9. No JavaScript errors in console
10. No PHP errors in logs

---

**Ready to test?** Follow the Quick Test Instructions above!

**Issues?** Check the Debugging section or the main PAYMENT_FIX_SUMMARY.md file.

