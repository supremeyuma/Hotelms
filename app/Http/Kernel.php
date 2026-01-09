<?php

namespace App\Http;

use Illuminate\Foundation\Http\Kernel as HttpKernel;
use Spatie\Permission\Middleware\RoleMiddleware;
use Spatie\Permission\Middleware\PermissionMiddleware;
use Spatie\Permission\Middleware\RoleOrPermissionMiddleware;

class Kernel extends HttpKernel
{
    /**
     * Global HTTP middleware stack.
     *
     * @var array<int, class-string|string>
     */
    protected $middleware = [
        \App\Http\Middleware\TrustProxies::class,
        \Illuminate\Http\Middleware\HandleCors::class,
        \App\Http\Middleware\PreventRequestsDuringMaintenance::class,
        \Illuminate\Foundation\Http\Middleware\ValidatePostSize::class,
        \App\Http\Middleware\TrimStrings::class,
        \Illuminate\Foundation\Http\Middleware\ConvertEmptyStringsToNull::class,
    ];

    /**
     * Middleware groups.
     *
     * @var array<string, array<int, class-string|string>>
     */
    protected $middlewareGroups = [
        'web' => [
            \App\Http\Middleware\EncryptCookies::class,
            \Illuminate\Cookie\Middleware\AddQueuedCookiesToResponse::class,
            \Illuminate\Session\Middleware\StartSession::class,
            \Illuminate\View\Middleware\ShareErrorsFromSession::class,
            \App\Http\Middleware\VerifyCsrfToken::class,
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
        ],

        'api' => [
            'throttle:api',
            \Illuminate\Routing\Middleware\SubstituteBindings::class,
        ],
    ];

    /**
     * Middleware aliases (Laravel 12+).
     *
     * @var array<string, class-string|string>
     */
    protected $middlewareAliases = [
        // Core
        'auth' => \App\Http\Middleware\Authenticate::class,
        'auth.basic' => \Illuminate\Auth\Middleware\AuthenticateWithBasicAuth::class,
        'auth.session' => \Illuminate\Session\Middleware\AuthenticateSession::class,
        'can' => \Illuminate\Auth\Middleware\Authorize::class,
        'guest' => \App\Http\Middleware\RedirectIfAuthenticated::class,
        'password.confirm' => \Illuminate\Auth\Middleware\RequirePassword::class,
        'signed' => \Illuminate\Routing\Middleware\ValidateSignature::class,
        'throttle' => \Illuminate\Routing\Middleware\ThrottleRequests::class,
        'verified' => \Illuminate\Auth\Middleware\EnsureEmailIsVerified::class,

        /**
         * ===============================
         * Spatie Roles & Permissions
         * ===============================
         */
        'role' => RoleMiddleware::class,
        'permission' => PermissionMiddleware::class,
        'role_or_permission' => RoleOrPermissionMiddleware::class,

        /**
         * ===============================
         * Hotel MS Custom Middleware
         * ===============================
         */
        'staff' => \App\Http\Middleware\EnsureStaff::class,
        'department' => \App\Http\Middleware\EnsureStaffDepartment::class,
        'staff.action' => \App\Http\Middleware\ValidateStaffActionCode::class,
        'audit' => \App\Http\Middleware\AuditRequest::class,
        'owns.booking' => \App\Http\Middleware\EnsureBookingOwnership::class,
        'owns.order' => \App\Http\Middleware\EnsureOrderOwnership::class,
    ];

    // app/Http/Kernel.php

    protected $routeMiddleware = [
        // ...
        'validate.room.token' => \App\Http\Middleware\ValidateRoomToken::class,
        'resolve.guest.room' => \App\Http\Middleware\ResolveGuestRoom::class,
    ];

}
