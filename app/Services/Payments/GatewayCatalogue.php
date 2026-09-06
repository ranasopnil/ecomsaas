<?php

namespace App\Services\Payments;

use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\GatewayAvailability;
use App\Models\Tenant;
use App\Models\TenantGatewayGrant;
use Illuminate\Support\Collection;

/**
 * Which gateways exist, where each is allowed, and what one shop may use.
 */
class GatewayCatalogue
{
    /**
     * @return Collection<string, array<string, mixed>>
     */
    public function all(): Collection
    {
        return collect(config('gateways'));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function find(string $gateway): ?array
    {
        return config("gateways.{$gateway}");
    }

    /**
     * Allowed in a country: what staff have decided, or what the code says
     * when they have not decided anything.
     */
    public function isAllowedIn(string $gateway, string $country): bool
    {
        $decision = GatewayAvailability::where('gateway', $gateway)->where('country_code', $country)->first();

        if ($decision !== null) {
            return $decision->is_allowed;
        }

        return $this->allowedByDefault($gateway, $country);
    }

    public function allowedByDefault(string $gateway, string $country): bool
    {
        $countries = $this->find($gateway)['countries'] ?? [];

        return in_array('*', $countries, true) || in_array($country, $countries, true);
    }

    public function setAllowed(string $gateway, string $country, bool $allowed): void
    {
        GatewayAvailability::updateOrCreate(
            ['gateway' => $gateway, 'country_code' => $country],
            ['is_allowed' => $allowed],
        );
    }

    /**
     * The whole allowed/not-allowed grid staff see.
     *
     * @return array<string, array<string, bool>> gateway => country => allowed
     */
    public function grid(): array
    {
        $decisions = GatewayAvailability::all()
            ->keyBy(fn (GatewayAvailability $row) => $row->gateway.'|'.$row->country_code);

        $grid = [];

        foreach ($this->all() as $gateway => $definition) {
            foreach (array_keys(config('countries')) as $country) {
                $decision = $decisions->get($gateway.'|'.$country);

                $grid[$gateway][$country] = $decision !== null
                    ? $decision->is_allowed
                    : $this->allowedByDefault($gateway, $country);
            }
        }

        return $grid;
    }

    /**
     * What one shop may switch on: allowed in its country, or granted to it,
     * and covered by its plan.
     *
     * @return Collection<string, array<string, mixed>> keyed by gateway, with 'reason' added
     */
    public function availableFor(Tenant $tenant): Collection
    {
        $granted = Tenancy::run($tenant, fn () => TenantGatewayGrant::query()->pluck('gateway')->all());

        return $this->all()
            ->map(function (array $definition, string $gateway) use ($tenant, $granted) {
                $byCountry = $this->isAllowedIn($gateway, $tenant->country_code);
                $byGrant = in_array($gateway, $granted, true);

                if (! $byCountry && ! $byGrant) {
                    return null;
                }

                $definition['reason'] = $byGrant && ! $byCountry ? 'granted' : 'country';
                $definition['covered_by_plan'] = Tenancy::run(
                    $tenant,
                    fn () => Entitlements::allows($definition['entitlement'] ?? 'online_payments')
                );

                return $definition;
            })
            ->filter();
    }

    public function isAvailableFor(Tenant $tenant, string $gateway): bool
    {
        return $this->availableFor($tenant)->has($gateway);
    }
}
