<?php

namespace App\Http\Middleware;

use App\Models\Content;
use App\Models\Setting;
use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'flash' => [
                'success' => fn () => $request->session()->get('success'),
                'error' => fn () => $request->session()->get('error'),
                // Task 2 acknowledgement: true when the sale is queued locally
                // because no peer acknowledged it, false when a peer already
                // has it. The POS screen confirms either outcome to the cashier.
                'queued_for_sync' => fn () => $request->session()->get('queued_for_sync'),
                // Set when a receipt reprint was queued for the print bridge.
                'receipt_queued' => fn () => $request->session()->get('receipt_queued'),
            ],
            'auth' => [
                'user' => fn () => $request->user()?->loadMissing('roles', 'department'),
            ],
            'site_content' => function () {
                return Content::all()->pluck('value', 'key');
            },
            'settings' => fn () => Setting::pluck('value', 'key'),
            'club_pos' => [
                'device_id' => fn () => request()->cookie('pos_device_id'),
                'pin_verified' => fn () => request()->session()->get('pos_pin_verified', false),
            ],
            // The licence desk is a platform-owner screen: it only exists where
            // licences can be signed, and only for the superuser. The sidebar
            // uses this to hide the entry on a hotel's own server.
            'licensing' => [
                'admin' => fn () => filled(config('licensing.private_key'))
                    && (bool) $request->user()?->hasRole('superuser'),
                'checkoutEnabled' => fn () => (bool) config('licensing.checkout_enabled'),
            ],
        ];
    }
}
