<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Makes the licence admin exist only on the deployment that can sign licences.
 *
 * The licence screen shows every customer's sale and can mint keys, so it is
 * hidden from a hotel's own installation rather than merely empty there: a hotel
 * admin must not see a "Licences" area at all. Activation answers 503 without
 * the signing key, and this answers 404 for the same reason.
 */
class EnsureLicenceAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (blank(config('licensing.private_key'))) {
            abort(404);
        }

        return $next($request);
    }
}
