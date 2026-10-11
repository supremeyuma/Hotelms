<?php

namespace App\Http\Controllers;

use App\Services\RoleRedirectService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class DashboardRedirectController extends Controller
{
    /**
     * Canonical post-authentication landing route.
     *
     * Every role has its own dashboard, so this route resolves the signed-in
     * user's landing page instead of rendering a screen of its own. It is the
     * target used after login, registration and email verification.
     */
    public function __invoke(Request $request, RoleRedirectService $redirectService): RedirectResponse
    {
        $destination = $redirectService->redirectPath($request->user());

        // Guard against a self-redirect: a user with no resolvable role lands
        // back on the login screen rather than looping on this route.
        if ($destination === route('dashboard')) {
            return redirect()->route('login');
        }

        return redirect($destination);
    }
}