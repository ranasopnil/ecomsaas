<?php

namespace App\Http\Middleware;

use App\Models\Subscription;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Closes a shop's own dashboard when it has not paid, and nothing else.
 *
 * The storefront is never touched by this. A shop whose dashboard is closed
 * keeps taking orders, and they are waiting for it the moment it pays: its
 * customers had no part in a billing problem and must not be punished for it.
 *
 * The plan page itself always stays open — otherwise a shop could not tell us
 * it had paid.
 */
class EnsureBillingCurrent
{
    /** The few things a locked-out shopkeeper can still reach. */
    protected const ALWAYS_OPEN = [
        'admin.plan.index',
        'admin.login',
        'admin.login.store',
        'admin.logout',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        if (in_array($request->route()?->getName(), self::ALWAYS_OPEN, true)) {
            return $next($request);
        }

        $subscription = Subscription::query()->active()->latest('id')->first();

        if ($subscription === null || ! $subscription->isLocked()) {
            return $next($request);
        }

        return redirect()->route('admin.plan.index');
    }
}
