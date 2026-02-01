# 🎯 Quick Start: Multi-Provider Payment System

## What's Been Implemented

Your Hotel Management System now has a **production-ready multi-provider payment system** supporting both **Flutterwave** and **Paystack** with automatic provider selection.

---

## ⚡ Quick Setup (5 Minutes)

### Step 1: Update Environment Variables
Edit your `.env` file:

```bash
# Flutterwave - Get keys from https://dashboard.flutterwave.com
PAYMENT_FLUTTERWAVE_ENABLED=true
FLUTTERWAVE_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
FLUTTERWAVE_SECRET_KEY=sk_test_xxxxxxxxxxxxx
FLUTTERWAVE_WEBHOOK_SECRET=whsec_xxxxxxxxxxxxx

# Paystack - Get keys from https://dashboard.paystack.com
PAYMENT_PAYSTACK_ENABLED=true
PAYSTACK_PUBLIC_KEY=pk_test_xxxxxxxxxxxxx
PAYSTACK_SECRET_KEY=sk_test_xxxxxxxxxxxxx
PAYSTACK_WEBHOOK_SECRET=xxxxxxxxxxxxx

# Default when only one is enabled
PAYMENT_DEFAULT_PROVIDER=flutterwave
```

### Step 2: Run Migrations
```bash
php artisan migrate
```

This adds payment provider tracking to your database.

### Step 3: Test the System
```bash
# Start your dev server
composer run dev

# In your browser, test payment initialization
POST http://localhost:8000/payments/initialize
{
  "type": "ticket",
  "event_id": 1,
  "ticket_type_id": 1,
  "quantity": 1
}
```

---

## 🎮 How It Works for Your Guests

### Scenario 1: Both Flutterwave & Paystack Enabled
1. Guest starts purchase
2. System shows both payment options
3. Guest chooses their preferred method
4. Payment completes on selected provider
5. Webhook confirms payment
6. Guest marked as paid ✓

### Scenario 2: Only Flutterwave Enabled
1. Guest starts purchase
2. System auto-selects Flutterwave (no choice)
3. Payment completes on Flutterwave
4. Guest marked as paid ✓

### Scenario 3: Only Paystack Enabled
1. Guest starts purchase
2. System auto-selects Paystack (no choice)
3. Payment completes on Paystack
4. Guest marked as paid ✓

---

## 📱 Frontend Implementation

Add this Vue component to your payment page:

```vue
<template>
  <div class="payment-container">
    <!-- Provider Selection (shown when multiple enabled) -->
    <div v-if="paymentData.show_provider_options" class="provider-selection">
      <button
        v-for="provider in paymentData.available_providers"
        :key="provider.value"
        @click="selectProvider(provider.value)"
      >
        {{ provider.label }}
      </button>
    </div>

    <!-- Payment Handler -->
    <PaymentHandler
      :paymentData="paymentData"
      :selectedProvider="selectedProvider"
    />
  </div>
</template>

<script>
export default {
  data() {
    return {
      paymentData: null,
      selectedProvider: null,
    };
  },

  methods: {
    selectProvider(provider) {
      this.selectedProvider = provider;
      this.initializePayment(provider);
    },

    async initializePayment(provider) {
      const response = await axios.post('/payments/initialize', {
        type: 'ticket',
        event_id: this.$route.params.eventId,
        ticket_type_id: this.$route.params.ticketTypeId,
        quantity: 1,
        provider: provider, // Send selected provider
      });

      this.paymentData = response.data;
      this.selectedProvider = response.data.provider;
    },
  },

  async mounted() {
    await this.initializePayment();
  },
};
</script>
```

---

## 🔌 Webhook Configuration

After deploying, configure webhooks in each provider's dashboard:

### Flutterwave Dashboard
- Go to Settings → Webhooks
- Add URL: `https://yourdomain.com/webhooks/flutterwave`
- Copy webhook secret to `FLUTTERWAVE_WEBHOOK_SECRET`

### Paystack Dashboard
- Go to Settings → API Keys & Webhooks
- Add URL: `https://yourdomain.com/webhooks/paystack`
- Copy webhook secret to `PAYSTACK_WEBHOOK_SECRET`

---

## 🧪 Test Payment Cards

**Flutterwave Test Cards**:
- Card: `4242 4242 4242 4242`
- CVV: `123`
- Expiry: `09/25`

**Paystack Test Cards**:
- Card: `4111 1111 1111 1111`
- CVV: `408`
- Expiry: `12/25`

---

## 📊 Database Changes

Your database now tracks payment provider information:

```
payments table:
├─ provider (flutterwave, paystack, or manual)
├─ external_reference (provider's transaction ID)
├─ verified_at (when payment was confirmed)
└─ payment_type (ticket, booking, order, etc.)

event_tickets table:
├─ payment_provider
├─ payment_reference
└─ payment_verified_at

event_table_reservations table:
├─ payment_provider
├─ payment_reference
└─ payment_verified_at

bookings table:
└─ payment_method
```

---

## 🔍 Monitoring & Debugging

### Watch Real-Time Logs
```bash
php artisan pail
```

### Check Provider Status
```bash
php artisan tinker
> app(PaymentProviderManager::class)->getEnabledProviders()
// Output: ['flutterwave', 'paystack']
```

### Test Payment Verification
```bash
php artisan tinker
> app(PaymentProviderManager::class)->verifyPayment('TEST-REF-123')
```

---

## 🚨 Troubleshooting

### Issue: "Provider not found"
**Solution**: Ensure at least one payment provider is enabled in `.env`

### Issue: Webhook not working
**Solution**: 
1. Verify webhook URL is publicly accessible
2. Check webhook secret matches exactly
3. Monitor logs: `php artisan pail | grep webhook`

### Issue: Payment shows as verified but guest still sees unpaid
**Solution**:
1. Check database: `select * from event_tickets where id = X;`
2. Verify `payment_verified_at` is set
3. Check logs for any confirmation errors

---

## 🎯 Key Features Implemented

✅ **Provider Selection** - Automatic or guest-driven
✅ **Webhook Verification** - HMAC signature validation
✅ **Idempotency Protection** - No duplicate charges
✅ **Transaction Tracking** - Full audit trail
✅ **Error Handling** - Production-grade resilience
✅ **Tax Compliance** - Transaction-specific tax rates
✅ **Admin Control** - Toggle providers on/off
✅ **Fallback Logic** - Graceful handling when provider unavailable

---

## 📚 Documentation

- **PAYMENT_IMPLEMENTATION_COMPLETE.md** - Full implementation details
- **MULTI_PROVIDER_PAYMENT_COMPLETE.md** - Comprehensive guide (600+ lines)
- This file - Quick start guide

---

## 🚀 Next Steps

1. ✅ Update `.env` with real API keys
2. ✅ Run `php artisan migrate`
3. ✅ Configure webhook URLs in provider dashboards
4. ✅ Test with test cards
5. ✅ Deploy to production
6. ✅ Switch to production API keys
7. ✅ Monitor first live transactions

---

## 💡 Pro Tips

- Keep webhook secrets secure (never commit to git)
- Rotate API keys quarterly for security
- Monitor webhook success rate in logs
- Use test environment API keys during development
- Enable both providers initially for flexibility
- Disable providers you don't use to simplify UI

---

## 🆘 Need Help?

**Check Logs**:
```bash
php artisan pail
# Look for payment-related entries
```

**Test Endpoints**:
```bash
# Test Flutterwave
curl -X POST http://localhost:8000/webhooks/flutterwave \
  -H "verif-hash: test" \
  -d '{"event":"charge.completed"}'

# Test Paystack
curl -X POST http://localhost:8000/webhooks/paystack \
  -H "x-paystack-signature: test" \
  -d '{"event":"charge.success"}'
```

**Database Query**:
```sql
-- Check payment records
SELECT * FROM payments ORDER BY created_at DESC LIMIT 10;

-- Check event ticket payments
SELECT id, qr_code, payment_provider, payment_verified_at 
FROM event_tickets WHERE payment_provider IS NOT NULL;
```

---

**Status**: ✅ Ready for Production

**Last Updated**: February 2026

