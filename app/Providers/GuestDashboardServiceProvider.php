<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Services\RoomServiceService;
use App\Services\MaintenanceService;
use App\Services\BillingService;
use App\Services\BookingExtensionService;
use App\Services\CheckoutService;

class GuestDashboardServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(RoomServiceService::class, function ($app) {
            return new RoomServiceService();
        });

        $this->app->singleton(MaintenanceService::class, function ($app) {
            return new MaintenanceService();
        });

        $this->app->singleton(BillingService::class, function ($app) {
            return new BillingService();
        });

        $this->app->singleton(BookingExtensionService::class, function ($app) {
            return new BookingExtensionService();
        });

        $this->app->singleton(CheckoutService::class, function ($app) {
            return new CheckoutService($app->make(BillingService::class));
        });
    }

    public function boot(): void
    {
        //
    }
}
