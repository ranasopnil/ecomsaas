<?php

namespace App\Http\Middleware;

use App\Facades\Tenancy;
use App\Services\Analytics\VisitRecorder;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;
use Throwable;

/**
 * Counts one visit to a shop's storefront.
 *
 * Only real people looking at real pages are counted: not search engines, not
 * the page the browser fetched in the background in case you click, and not
 * the shopkeeper's own visits to their own shop.
 */
class CountVisit
{
    /** Anything that says it is a machine. */
    protected const MACHINES = '/bot|crawler|crawl|spider|slurp|bingpreview|facebookexternalhit|headless|preview|monitor|uptime|curl|wget|python-requests|okhttp|libwww|scrapy|ahrefs|semrush|lighthouse/i';

    public function __construct(protected VisitRecorder $recorder) {}

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $store = Tenancy::current();

        if ($store !== null && $this->worthCounting($request, $response)) {
            try {
                $this->recorder->handle($request, $store);
            } catch (Throwable $e) {
                // Counting a visit must never take the shop down.
                report($e);
            }
        }

        return $response;
    }

    protected function worthCounting(Request $request, Response $response): bool
    {
        if (! $request->isMethod('GET') || $request->ajax() || $request->hasHeader('X-Livewire')) {
            return false;
        }

        if ($response->getStatusCode() !== 200) {
            return false;
        }

        if (! str_contains((string) $response->headers->get('Content-Type'), 'text/html')) {
            return false;
        }

        // The shopkeeper and their staff looking at their own shop.
        if (Auth::guard('web')->check()) {
            return false;
        }

        return ! $this->fetchedInAdvance($request) && ! $this->isMachine($request);
    }

    /**
     * A browser quietly loading the page in case the person clicks. Nobody has
     * looked at anything yet, so it is not a visit.
     */
    protected function fetchedInAdvance(Request $request): bool
    {
        return $request->header('Purpose') === 'prefetch'
            || $request->header('X-Purpose') === 'preview'
            || str_contains((string) $request->header('Sec-Purpose'), 'prefetch');
    }

    protected function isMachine(Request $request): bool
    {
        $agent = (string) $request->userAgent();

        return $agent === '' || preg_match(self::MACHINES, $agent) === 1;
    }
}
