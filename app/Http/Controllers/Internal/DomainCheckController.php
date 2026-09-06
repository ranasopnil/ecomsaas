<?php

namespace App\Http\Controllers\Internal;

use App\Http\Controllers\Controller;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * Caddy asks this before it issues an HTTPS certificate for a hostname.
 *
 * 200 means "yes, this hostname is ours". Anything else means no, which is
 * what stops strangers pointing their domain at us and getting a certificate.
 */
class DomainCheckController extends Controller
{
    public function __invoke(Request $request): Response
    {
        if (! in_array($request->ip(), config('tenancy.internal_ips'), true)) {
            abort(404);
        }

        $hostname = strtolower(trim((string) $request->query('domain')));

        if ($hostname === '') {
            abort(404);
        }

        if (in_array($hostname, config('tenancy.central_domains'), true)) {
            return response('ok', 200);
        }

        $domain = Domain::findByHostname($hostname);

        abort_if($domain === null || ! $domain->isUsable(), 404);
        abort_if($domain->tenant === null || $domain->tenant->status === Tenant::STATUS_SUSPENDED, 404);

        return response('ok', 200);
    }
}
