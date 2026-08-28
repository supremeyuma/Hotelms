<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;
use App\Models\Content;
use App\Models\Setting;

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
        ];
    }
}
