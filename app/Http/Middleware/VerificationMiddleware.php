<?php

namespace App\Http\Middleware;

use App\Models\Role;
use Closure;
use Illuminate\Support\Facades\Gate;

class VerificationMiddleware
{
    public function handle($request, Closure $next)
    {
        // Email verification is intentionally disabled for this project.
        // User activation/deactivation is managed by administrators from the admin portal.
        return $next($request);
    }
}
