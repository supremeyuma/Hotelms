<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Auth;
use App\Models\Booking;
use App\Observers\BookingObserver;
use App\Services\ContentService;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        Inertia::share([
            'auth' => function () {
                $user = Auth::user();
                return [
                    'user' => $user ? [
                        'id' => $user->id,
                        'name' => $user->name,
                        'role' => $user->role?->name ?? null, // <-- fetch role name via relationship
                    ] : null,
                ];
            },
        ]);

        Inertia::share('content', function () {
            return app(ContentService::class)->all();
        });


        Booking::observe(BookingObserver::class);
        
    }
}
