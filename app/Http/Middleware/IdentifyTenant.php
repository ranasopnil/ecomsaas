<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Tenant;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Works out which store a request is for, from the hostname alone.
 */
class IdentifyTenant
{
    public function handle(Request $request, Closure $next): Response
    {
        $host = strtolower($request->getHost());

        if ($this->isCentral($host, $request)) {
            Tenancy::forget();

            return $next($request);
        }

        $domain = Domain::findByHostname($host);

        abort_if($domain === null || ! $domain->isUsable(), 404);

        $tenant = $domain->tenant;

        abort_if($tenant === null || $tenant->status === Tenant::STATUS_SUSPENDED, 404);

        Tenancy::set($tenant);

        config(['app.timezone' => $tenant->timezone]);
        date_default_timezone_set($tenant->timezone);

        return $next($request);
    }

    protected function isCentral(string $host, Request $request): bool
    {
        if (in_array($host, config('tenancy.central_domains'), true)) {
            return true;
        }

        return $request->is(...config('tenancy.central_paths'));
    }
}
