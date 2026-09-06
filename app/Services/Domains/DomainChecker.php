<?php

namespace App\Services\Domains;

use App\Models\Domain;
use Illuminate\Support\Facades\Log;

/**
 * Looks up where a merchant's domain currently points.
 *
 * A domain that resolves to this server is proof enough that the merchant
 * controls it: nobody else can make someone else's domain point here. That is
 * why there is no separate code to paste into a TXT record.
 */
class DomainChecker
{
    public function __construct(protected DnsResolver $dns) {}

    /**
     * Returns true when the domain now points at us.
     */
    public function check(Domain $domain): bool
    {
        if ($domain->type === Domain::TYPE_SUBDOMAIN) {
            return true;
        }

        $ours = config('tenancy.server_ips');
        $found = $this->dns->addressesFor($domain->hostname);

        $pointsHere = array_intersect($found, $ours) !== [];

        $domain->forceFill([
            'last_checked_at' => now(),
            'last_check_result' => $this->describe($found, $ours, $pointsHere),
        ]);

        if ($pointsHere && $domain->status !== Domain::STATUS_VERIFIED) {
            $domain->forceFill([
                'status' => Domain::STATUS_VERIFIED,
                'verified_at' => now(),
            ]);

            Log::info('Custom domain verified', ['hostname' => $domain->hostname, 'tenant_id' => $domain->tenant_id]);
        }

        $domain->save();

        return $pointsHere;
    }

    /**
     * Plain words the merchant can act on.
     *
     * @param  array<int, string>  $found
     * @param  array<int, string>  $ours
     */
    protected function describe(array $found, array $ours, bool $pointsHere): string
    {
        if ($pointsHere) {
            return 'Pointing here correctly.';
        }

        if ($found === []) {
            return 'No A record found yet. It can take a few minutes to a few hours after you add it.';
        }

        if ($this->looksLikeCloudflare($found)) {
            return 'This is going through Cloudflare. Set the record to DNS only (the grey cloud) so it reaches us directly.';
        }

        return 'Currently pointing at '.implode(', ', array_slice($found, 0, 3)).'. It needs to point at '.$ours[0].'.';
    }

    /**
     * @param  array<int, string>  $addresses
     */
    protected function looksLikeCloudflare(array $addresses): bool
    {
        // The ranges Cloudflare serves proxied traffic from.
        $prefixes = ['104.16.', '104.17.', '104.18.', '104.19.', '104.20.', '104.21.', '104.22.', '104.23.',
            '104.24.', '104.25.', '104.26.', '104.27.', '172.64.', '172.65.', '172.66.', '172.67.',
            '188.114.', '190.93.', '197.234.', '198.41.', '162.158.', '162.159.', '173.245.'];

        foreach ($addresses as $address) {
            foreach ($prefixes as $prefix) {
                if (str_starts_with($address, $prefix)) {
                    return true;
                }
            }
        }

        return false;
    }
}
