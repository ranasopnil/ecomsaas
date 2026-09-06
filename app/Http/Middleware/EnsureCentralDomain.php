<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Keeps platform-only pages off shop addresses.
 *
 * Without this, the super admin would answer on every merchant's own domain.
 */
class EnsureCentralDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_if(Tenancy::check(), 404);

        return $next($request);
    }
}
