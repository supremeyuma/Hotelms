<?php

namespace App\Providers;

use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Inertia\Inertia;
use Illuminate\Support\Facades\Schema;
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
        
    }
}
