<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Models\Order;
use App\Observers\OrderObserver;
use App\Models\LaundryOrder;
use App\Observers\LaundryOrderObserver;
use App\Models\MaintenanceTicket;
use App\Observers\MaintenanceTicketObserver;
use App\Models\Charge;
use App\Observers\ChargeObserver;
use App\Models\Payment;
use App\Observers\PaymentObserver;
use App\Services\ContentService;
use App\Services\Accounting\TaxService;
use App\Services\AccountingService;
use App\Services\PricingService;
use App\Services\PaymentProviderManager;
use App\Services\PaystackService;
use App\Services\FlutterwaveService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        // Register TaxService with its dependency
        $this->app->singleton(TaxService::class, function ($app) {
            return new TaxService($app->make(AccountingService::class));
        });

        // Register PricingService with TaxService dependency
        $this->app->singleton(PricingService::class, function ($app) {
            return new PricingService($app->make(TaxService::class));
        });

        // Register payment providers
        $this->app->singleton(FlutterwaveService::class, function ($app) {
            return new FlutterwaveService();
        });

        $this->app->singleton(PaystackService::class, function ($app) {
            return new PaystackService();
        });

        // Register payment provider manager
        $this->app->singleton(PaymentProviderManager::class, function ($app) {
            return new PaymentProviderManager(
                $app->make(FlutterwaveService::class),
                $app->make(PaystackService::class)
            );
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Gate::before(function ($user, string $ability) {
            return $user->hasRole('superuser') ? true : null;
        });

        Inertia::share([
            'auth' => function () {
                $user = Auth::user();

                $primaryRole = $user?->loadMissing('roles')->roles->first()?->name;

                return [
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'role' => $primaryRole,
                    ] : null,
                ];
            },
        ]);

        Inertia::share('content', function () {
            // Avoid querying the `contents` table when it doesn't exist yet
            if (!Schema::hasTable('contents')) {
                return [];
            }

            try {
                return app(ContentService::class)->all();
            } catch (\Throwable $e) {
                // If anything goes wrong (e.g., during migrations), return empty array
                return [];
            }
        });


        Booking::observe(BookingObserver::class);
        Order::observe(OrderObserver::class);
        LaundryOrder::observe(LaundryOrderObserver::class);
        MaintenanceTicket::observe(MaintenanceTicketObserver::class);
        Charge::observe(ChargeObserver::class);
        Payment::observe(PaymentObserver::class);
        
    }
}
