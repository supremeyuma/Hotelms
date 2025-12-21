<?php

// app/Http/Middleware/CleaningStaffOnly.php
namespace App\Http\Middleware;

use Closure;

class CleaningStaffOnly
{
    public function handle($request, Closure $next)
    {
        $user = $request->user();

        abort_if(
            !$user || !$user->department || $user->department->name !== 'Cleaning',
            403
        );

        return $next($request);
    }
}
