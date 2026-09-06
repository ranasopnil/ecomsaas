<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Shop admin pages exist only on a shop's own address.
 */
class EnsureStoreDomain
{
    public function handle(Request $request, Closure $next): Response
    {
        abort_unless(Tenancy::check(), 404);

        return $next($request);
    }
}
