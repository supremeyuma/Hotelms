<?php

namespace App\Services;

use Illuminate\Support\Facades\Log;
use Exception;

/**
 * PaymentProviderManager: Central hub for payment provider management
 * 
 * Manages:
 * - Provider enablement/disablement
 * - Provider selection logic
 * - Dynamic payment method resolution
 * - Fallback handling
 */
class PaymentProviderManager
{
    protected FlutterwaveService $flutterwave;
    protected PaystackService $paystack;

    public function __construct(
        FlutterwaveService $flutterwave,
        PaystackService $paystack
    ) {
        $this->flutterwave = $flutterwave;
        $this->paystack = $paystack;
    }

    /**
     * Get list of enabled payment providers
     * 
     * @return array List of provider keys
     */
    public function getEnabledProviders(): array
    {
        $enabled = [];
        $providers = config('payment.providers', []);

        foreach ($providers as $provider => $isEnabled) {
            if ($isEnabled) {
                $enabled[] = $provider;
            }
        }

        if (empty($enabled)) {
            Log::warning('No payment providers are enabled. Using default provider.');
            return [$this->getDefaultProvider()];
        }

        return $enabled;
    }

    /**
     * Check if a specific provider is enabled
     * 
     * @param string $provider Provider name (flutterwave, paystack)
     * @return bool
     */
    public function isProviderEnabled(string $provider): bool
    {
        return config("payment.providers.{$provider}", false) === true;
    }

    /**
     * Get available payment methods for display
     * Only includes enabled providers
     * 
     * @return array
     */
    public function getAvailablePaymentMethods(): array
    {
        $methods = [];
        $enabledProviders = $this->getEnabledProviders();

        foreach ($enabledProviders as $provider) {
            try {
                $service = $this->getProviderService($provider);
                $providerMethods = $service->getAvailablePaymentMethods();

                if (is_array($providerMethods)) {
                    $methods = array_merge($methods, $providerMethods);
                }
            } catch (Exception $e) {
                Log::warning("Failed to get payment methods for provider: {$provider}", [
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return $methods;
    }

    /**
     * Determine if customer should see payment provider options
     * 
     * @return bool True if multiple providers enabled, false if only one
     */
    public function shouldShowProviderOptions(): bool
    {
        return count($this->getEnabledProviders()) > 1;
    }

    /**
     * Get the default or only enabled provider
     * 
     * @return string Provider name
     */
    public function getDefaultProvider(): string
    {
        $enabled = $this->getEnabledProviders();

        if (count($enabled) === 1) {
            return $enabled[0];
        }

        return config('payment.default', 'flutterwave');
    }

    /**
     * Get service instance for a provider
     * 
     * @param string $provider Provider name
     * @return FlutterwaveService|PaystackService
     * @throws Exception
     */
    public function getProviderService(string $provider)
    {
        return match($provider) {
            'flutterwave' => $this->flutterwave,
            'paystack' => $this->paystack,
            default => throw new Exception("Unknown payment provider: {$provider}")
        };
    }

    /**
     * Initialize payment with specified provider
     * Falls back to default if provider not enabled
     * 
     * @param string $provider Provider name
     * @param array $paymentData Payment data
     * @return array
     */
    public function initializePayment(string $provider, array $paymentData): array
    {
        try {
            // Fall back to default if provider not enabled
            if (!$this->isProviderEnabled($provider)) {
                Log::warning("Provider {$provider} is not enabled. Using default provider.", [
                    'requested_provider' => $provider,
                ]);
                $provider = $this->getDefaultProvider();
            }

            $service = $this->getProviderService($provider);
            $result = $service->initializePayment($paymentData);

            // Add provider info to result
            $result['provider'] = $provider;

            return $result;
        } catch (Exception $e) {
            Log::error("Payment initialization failed", [
                'provider' => $provider,
                'error' => $e->getMessage(),
            ]);

            return [
                'success' => false,
                'error' => 'Payment initialization failed',
                'provider' => $provider,
            ];
        }
    }

    /**
     * Verify payment with appropriate provider
     * Attempts to verify with all providers if not specified
     * 
     * @param string $reference Payment reference
     * @param string|null $provider Provider name (optional)
     * @return array
     */
    public function verifyPayment(string $reference, ?string $provider = null): array
    {
        try {
            if ($provider && $this->isProviderEnabled($provider)) {
                $service = $this->getProviderService($provider);
                return $service->verifyPayment($reference);
            }

            // Try all enabled providers
            $enabledProviders = $this->getEnabledProviders();

            foreach ($enabledProviders as $p) {
                try {
                    $service = $this->getProviderService($p);
                    $result = $service->verifyPayment($reference);

                    if ($result['verified'] ?? false) {
                        $result['provider'] = $p;
                        return $result;
                    }
                } catch (Exception $e) {
                    Log::debug("Payment verification failed for provider: {$p}", [
                        'error' => $e->getMessage(),
                    ]);
                    continue;
                }
            }

            return [
                'success' => false,
                'verified' => false,
                'error' => 'Payment verification failed for all providers',
            ];
        } catch (Exception $e) {
            Log::error("Payment verification exception: " . $e->getMessage());

            return [
                'success' => false,
                'verified' => false,
                'error' => 'Payment verification failed',
            ];
        }
    }

    /**
     * Get provider label for display
     * 
     * @param string $provider Provider name
     * @return string Display label
     */
    public function getProviderLabel(string $provider): string
    {
        return config("payment.provider_labels.{$provider}", ucfirst($provider));
    }

    /**
     * Get public key for frontend
     * 
     * @param string|null $provider Provider name (uses default if not specified)
     * @return string|null
     */
    public function getPublicKey(?string $provider = null): ?string
    {
        if (!$provider) {
            $provider = $this->getDefaultProvider();
        }

        try {
            $service = $this->getProviderService($provider);

            if (method_exists($service, 'getPublicKey')) {
                return $service->getPublicKey();
            }

            // Fallback for Flutterwave
            if ($provider === 'flutterwave') {
                return config('payment.flutterwave.public_key');
            }

            return null;
        } catch (Exception $e) {
            Log::error("Failed to get public key for provider: {$provider}", [
                'error' => $e->getMessage(),
            ]);
            return null;
        }
    }
}
