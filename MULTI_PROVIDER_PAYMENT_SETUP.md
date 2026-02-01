# Multi-Provider Payment Integration (Flutterwave + Paystack)

## Overview

This production-ready implementation adds support for multiple payment providers with intelligent provider selection. Guests can choose between payment methods when multiple are enabled, or use the only available method when one is enabled.

## Files Created/Modified

### New Files
1. **`config/payment.php`** - Central payment configuration
2. **`app/Services/PaystackService.php`** - Paystack integration service  
3. **`app/Services/PaymentProviderManager.php`** - Provider orchestration
4. **`app/Http/Controllers/WebhookController.php`** - Webhook handlers

### Modified Files  
1. **`app/Http/Controllers/PaymentController.php`** - Multi-provider payment initialization
2. **`app/Services/EventService.php`** - Already updated with tax rates
3. **`app/Services/BookingService.php`** - Already updated
4. **`app/Services/OrderService.php`** - Already updated
5. **`app/Services/LaundryOrderService.php`** - Already updated

## Database Migration Required

```php
// database/migrations/TIMESTAMP_add_provider_to_payments_table.php

Schema::table('payments', function (Blueprint $table) {
    $table->string('provider')->default('flutterwave')->after('status');
    $table->string('external_reference')->nullable()->after('provider');
    $table->timestamp('verified_at')->nullable()->after('external_reference');
});

Schema::table('event_tickets', function (Blueprint $table) {
    $table->string('payment_provider')->default('flutterwave')->nullable()->after('payment_method');
});

Schema::table('event_table_reservations', function (Blueprint $table) {
    $table->string('payment_provider')->default('flutterwave')->nullable()->after('payment_method');
});
```

## Environment Configuration

Add these to `.env`:

```env
# Payment Provider Enablement
PAYMENT_FLUTTERWAVE_ENABLED=true
PAYMENT_PAYSTACK_ENABLED=true
PAYMENT_DEFAULT_PROVIDER=flutterwave

# Flutterwave
FLUTTERWAVE_PUBLIC_KEY=your_flutterwave_public_key
FLUTTERWAVE_SECRET_KEY=your_flutterwave_secret_key
FLUTTERWAVE_SECRET_HASH=your_flutterwave_secret_hash
FLUTTERWAVE_TIMEOUT=30

# Paystack
PAYSTACK_PUBLIC_KEY=your_paystack_public_key
PAYSTACK_SECRET_KEY=your_paystack_secret_key
PAYSTACK_WEBHOOK_SECRET=your_paystack_webhook_secret
PAYSTACK_TIMEOUT=30

# Payment Logging
PAYMENT_LOGGING_ENABLED=true
PAYMENT_LOG_CHANNEL=single
```

## Routes to Add

Add to `routes/web.php`:

```php
// Webhook Routes
Route::post('/webhooks/flutterwave', [WebhookController::class, 'handleFlutterwaveWebhook'])
    ->name('webhook.flutterwave')
    ->withoutMiddleware('web');

Route::post('/webhooks/paystack', [WebhookController::class, 'handlePaystackWebhook'])
    ->name('webhook.paystack')
    ->withoutMiddleware('web');

// Payment Routes  
Route::middleware('web')->group(function () {
    Route::post('/payments/initialize', [PaymentController::class, 'initialize'])
        ->name('payment.initialize');
    
    Route::post('/payments/initialize-by-reference', [PaymentController::class, 'initializeByReference'])
        ->name('payment.initializeByReference');
    
    Route::post('/payments/verify', [PaymentController::class, 'verify'])
        ->name('payment.verify');
    
    Route::get('/payments/methods', [PaymentController::class, 'getMethods'])
        ->name('payment.methods');
});
```

## Payment Flow Architecture

### Flow Diagram

```
Guest Initiates Payment
    ↓
PaymentController.initialize() / initializeByReference()
    ↓
PaymentProviderManager.getEnabledProviders()
    ├─ Multiple Enabled? → Show provider selection UI
    └─ Single Enabled? → Use automatically
    ↓
Front-end shows:
  - Provider options (if multiple)
  - Provider-specific checkout form
  - Public key for selected provider
    ↓
Guest selects provider and completes payment
    ↓
Payment Provider Webhook → WebhookController
    ├─ Validate signature
    ├─ Verify transaction
    ├─ Update payment record
    ├─ Confirm event/booking
    └─ Return success
```

### API Responses

**Initialize Payment Response:**
```json
{
  "success": true,
  "reference": "EVT-ABC12345-1234567890",
  "amount": 293750,
  "currency": "NGN",
  "provider": "flutterwave",
  "show_provider_options": true,
  "available_providers": [
    {
      "value": "flutterwave",
      "label": "Flutterwave",
      "description": "Secure payment with card, bank transfer, USSD, or mobile money"
    },
    {
      "value": "paystack",
      "label": "Paystack",
      "description": "Secure payment with card, bank transfer, or USSD"
    }
  ],
  "public_key": "pk_live_...",
  "description": "Event Ticket: Summer Concert 2026",
  "customer": {
    "email": "guest@example.com",
    "name": "John Doe"
  },
  "meta": {
    "event": "Summer Concert 2026",
    "ticketType": "VIP",
    "quantity": 2
  }
}
```

## Frontend Implementation

### Vue Component Example

```vue
<template>
  <div v-if="loading" class="spinner">Loading payment options...</div>
  
  <div v-else-if="showProviderOptions" class="provider-selection">
    <h3>Choose Payment Method</h3>
    <div class="provider-options">
      <button
        v-for="method in availableMethods"
        :key="method.value"
        @click="selectProvider(method.value)"
        class="provider-card"
      >
        <h4>{{ method.label }}</h4>
        <p>{{ method.description }}</p>
      </button>
    </div>
  </div>
  
  <PaymentCheckout 
    :provider="selectedProvider"
    :paymentData="paymentData"
    @completed="handlePaymentComplete"
  />
</template>

<script setup>
import { ref, onMounted } from 'vue'

const loading = ref(true)
const selectedProvider = ref(null)
const showProviderOptions = ref(false)
const availableMethods = ref([])
const paymentData = ref(null)

onMounted(async () => {
  try {
    const response = await fetch('/payments/initialize-by-reference', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ reference: route('reference') })
    })
    
    const data = await response.json()
    
    showProviderOptions.value = data.show_provider_options
    availableMethods.value = data.available_providers
    paymentData.value = data
    selectedProvider.value = data.provider
  } finally {
    loading.value = false
  }
})

const selectProvider = (provider) => {
  selectedProvider.value = provider
}

const handlePaymentComplete = async (result) => {
  // Verify payment with backend
  await fetch('/payments/verify', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({
      reference: result.reference,
      provider: selectedProvider.value
    })
  })
  
  window.location.href = `/events/success?reference=${result.reference}`
}
</script>
```

## Service Provider Registration

Update `app/Providers/AppServiceProvider.php`:

```php
public function register(): void
{
    // ... existing bindings ...
    
    // Payment Services
    $this->app->singleton(PaystackService::class, function ($app) {
        return new PaystackService();
    });

    $this->app->singleton(PaymentProviderManager::class, function ($app) {
        return new PaymentProviderManager(
            $app->make(FlutterwaveService::class),
            $app->make(PaystackService::class)
        );
    });
}
```

## Admin Settings UI

Create `resources/js/Pages/Admin/PaymentSettings.vue`:

```vue
<template>
  <div class="payment-settings">
    <h1>Payment Gateway Settings</h1>
    
    <div class="provider-toggles">
      <div v-for="provider in providers" :key="provider.id" class="provider-card">
        <h3>{{ provider.name }}</h3>
        <p>{{ provider.description }}</p>
        
        <div class="toggle">
          <label>
            <input 
              type="checkbox" 
              :checked="provider.enabled"
              @change="toggleProvider(provider)"
            />
            {{ provider.enabled ? 'Enabled' : 'Disabled' }}
          </label>
        </div>
        
        <div v-if="provider.enabled" class="config">
          <div class="form-group">
            <label>Public Key</label>
            <input 
              type="text" 
              :value="provider.public_key"
              @input="updateConfig(provider, 'public_key', $event)"
            />
          </div>
          <div class="form-group">
            <label>Secret Key</label>
            <input 
              type="password"
              @input="updateConfig(provider, 'secret_key', $event)"
            />
          </div>
        </div>
      </div>
    </div>
    
    <button @click="save" class="btn btn-primary">Save Settings</button>
  </div>
</template>

<script setup>
import { ref } from 'vue'

const providers = ref([
  {
    id: 'flutterwave',
    name: 'Flutterwave',
    description: 'Process payments via Flutterwave',
    enabled: true,
    public_key: '',
    secret_key: ''
  },
  {
    id: 'paystack',
    name: 'Paystack',
    description: 'Process payments via Paystack',
    enabled: false,
    public_key: '',
    secret_key: ''
  }
])

const toggleProvider = (provider) => {
  provider.enabled = !provider.enabled
}

const updateConfig = (provider, field, event) => {
  provider[field] = event.target.value
}

const save = async () => {
  await fetch('/admin/payment-settings', {
    method: 'POST',
    body: JSON.stringify({ providers: providers.value })
  })
  alert('Settings saved!')
}
</script>
```

## Production Checklist

- [ ] All environment variables configured in `.env`
- [ ] Database migrations run
- [ ] Webhook URLs registered in Flutterwave dashboard
- [ ] Webhook URLs registered in Paystack dashboard
- [ ] Admin payment settings page created
- [ ] Frontend payment components updated
- [ ] Rate limiting added to webhook endpoints
- [ ] Error logging configured
- [ ] Payment timeouts tested
- [ ] Signature verification tested
- [ ] Refund flows tested for both providers
- [ ] Load testing completed
- [ ] Security audit completed
- [ ] Payment testing in production account (not test account)

## Webhook URLs (Provide to Payment Providers)

```
Flutterwave: https://yourhotel.com/webhooks/flutterwave
Paystack: https://yourhotel.com/webhooks/paystack
```

## Key Features

✅ Multiple payment provider support
✅ Intelligent provider selection
✅ Provider-specific error handling
✅ Production-ready signature verification
✅ Comprehensive logging
✅ Automatic fallback to default provider
✅ Admin toggle for enabling/disabling providers
✅ Seamless customer experience
✅ Full transaction tracking
✅ Webhook idempotency
✅ Tax-aware pricing (already implemented)

## Testing

Run comprehensive tests:

```bash
# Test Flutterwave payment flow
php artisan test tests/Feature/Payments/FlutterwavePaymentTest.php

# Test Paystack payment flow
php artisan test tests/Feature/Payments/PaystackPaymentTest.php

# Test multi-provider selection
php artisan test tests/Feature/Payments/MultiProviderTest.php
```

## Troubleshooting

**Webhook not being called?**
- Verify webhook URL is correctly registered in provider dashboard
- Check firewall/server logs for incoming POST requests
- Ensure signature validation is passing (check logs)

**Payment stuck in pending?**
- Check webhook logs for errors
- Verify provider transaction status in dashboard
- Manual verification available in admin

**Provider selection not showing?**
- Verify both providers are enabled in `.env`
- Check `PAYMENT_DEFAULT_PROVIDER` setting
- Clear config cache: `php artisan config:clear`

## Future Enhancements

- [ ] Payment plan / subscription support
- [ ] Multiple currency support
- [ ] Payment splitting/commission handling
- [ ] Advanced fraud detection
- [ ] Real-time payment status dashboard
- [ ] Mobile wallet integrations (Apple Pay, Google Pay)
