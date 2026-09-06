<?php

namespace App\Services\Domains;

use App\Exceptions\LimitReached;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Domain;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Adding and removing a merchant's own web address.
 */
class DomainRegistrar
{
    /**
     * Tidy up whatever the merchant pasted in: an address, a link, with or
     * without https, with or without a trailing slash.
     */
    public function tidy(string $input): string
    {
        $hostname = strtolower(trim($input));
        $hostname = preg_replace('~^[a-z]+://~', '', $hostname) ?? $hostname;
        $hostname = explode('/', $hostname)[0];
        $hostname = explode(':', $hostname)[0];

        return trim($hostname, '.');
    }

    /**
     * @throws InvalidArgumentException when the address itself is wrong
     * @throws LimitReached when the plan does not allow another one
     */
    public function add(Tenant $tenant, string $input, bool $withWww = true): Domain
    {
        $hostname = $this->tidy($input);

        $this->assertUsable($hostname);

        return DB::transaction(function () use ($tenant, $hostname, $withWww) {
            return Tenancy::run($tenant, function () use ($tenant, $hostname, $withWww) {
                // Only addresses the merchant owns count against the plan.
                Entitlements::ensureCanAdd(
                    'custom_domains',
                    Domain::where('type', Domain::TYPE_CUSTOM)->where('hostname', 'not like', 'www.%')->count(),
                );

                $domain = Domain::create([
                    'tenant_id' => $tenant->id,
                    'hostname' => $hostname,
                    'type' => Domain::TYPE_CUSTOM,
                    'status' => Domain::STATUS_PENDING,
                    'is_primary' => false,
                ]);

                // www is the same shop, and merchants expect both to work.
                if ($withWww && ! str_starts_with($hostname, 'www.') && substr_count($hostname, '.') === 1) {
                    $www = 'www.'.$hostname;

                    if (Domain::findByHostname($www) === null) {
                        Domain::create([
                            'tenant_id' => $tenant->id,
                            'hostname' => $www,
                            'type' => Domain::TYPE_CUSTOM,
                            'status' => Domain::STATUS_PENDING,
                            'is_primary' => false,
                        ]);
                    }
                }

                return $domain;
            });
        });
    }

    public function remove(Domain $domain): void
    {
        if ($domain->type === Domain::TYPE_SUBDOMAIN) {
            throw new InvalidArgumentException('The free shop address cannot be removed.');
        }

        Domain::where('hostname', 'www.'.$domain->hostname)->delete();

        $domain->delete();

        // Something has to be the shop's main address.
        if (! Domain::where('is_primary', true)->exists()) {
            Domain::orderByDesc('type')->orderBy('id')->first()?->update(['is_primary' => true]);
        }
    }

    /**
     * Make one address the shop's main one.
     */
    public function makePrimary(Domain $domain): void
    {
        if (! $domain->isUsable()) {
            throw new InvalidArgumentException('An address has to be working before it can be the main one.');
        }

        Domain::query()->update(['is_primary' => false]);
        $domain->update(['is_primary' => true]);
    }

    protected function assertUsable(string $hostname): void
    {
        if ($hostname === '' || ! preg_match('~^(?=.{4,253}$)([a-z0-9](-?[a-z0-9])*\.)+[a-z]{2,}$~', $hostname)) {
            throw new InvalidArgumentException('That does not look like a web address. Enter something like myshop.com.');
        }

        $suffix = config('tenancy.subdomain_suffix');

        if (str_ends_with($hostname, $suffix) || in_array($hostname, config('tenancy.central_domains'), true)) {
            throw new InvalidArgumentException('That address belongs to the platform. Enter a domain you own.');
        }

        if (Domain::findByHostname($hostname) !== null) {
            throw new InvalidArgumentException('That address is already in use.');
        }
    }
}
