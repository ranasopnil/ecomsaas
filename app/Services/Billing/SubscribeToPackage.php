<?php

namespace App\Services\Billing;

use App\Exceptions\PlanNotPricedInCurrency;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Models\TenantEntitlement;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

/**
 * Puts a store on a package.
 *
 * Any subscription the store already had is ended, never edited, and a new one
 * is written with the price agreed today. What the store is allowed to do is
 * then rewritten from the new package.
 */
class SubscribeToPackage
{
    public function handle(Tenant $tenant, Package $package, ?Carbon $startsAt = null): Subscription
    {
        $startsAt = $startsAt ?? now();

        $price = $package->priceIn($tenant->currency);

        if ($price === null) {
            throw PlanNotPricedInCurrency::make($package->name, $tenant->currency);
        }

        return DB::transaction(fn () => Tenancy::run($tenant, function () use ($tenant, $package, $price, $startsAt) {
            $isFirstEver = ! Subscription::query()->exists();

            $this->endCurrent($startsAt);

            $trialEndsAt = $isFirstEver && $package->trial_days > 0
                ? $startsAt->copy()->addDays($package->trial_days)
                : null;

            $subscription = Subscription::create([
                'tenant_id' => $tenant->id,
                'package_id' => $package->id,
                'status' => $trialEndsAt ? Subscription::STATUS_TRIALING : Subscription::STATUS_ACTIVE,
                'price_minor' => $price->minor,
                'currency' => $price->currency,
                'currency_exponent' => $price->exponent,
                'billing_period' => $package->billing_period,
                'starts_at' => $startsAt,
                'trial_ends_at' => $trialEndsAt,
                'current_period_ends_at' => $this->periodEnd($package, $trialEndsAt ?? $startsAt),
            ]);

            $this->syncEntitlements($tenant, $package);

            return $subscription;
        }));
    }

    /**
     * Rewrite what the store may do, leaving super-admin overrides alone.
     */
    public function syncEntitlements(Tenant $tenant, Package $package): void
    {
        Tenancy::run($tenant, function () use ($tenant, $package) {
            $features = [];

            foreach ($package->entitlements as $entitlement) {
                $features[] = $entitlement->feature;

                $existing = TenantEntitlement::query()
                    ->where('feature', $entitlement->feature)
                    ->first();

                if ($existing && $existing->source === TenantEntitlement::SOURCE_OVERRIDE) {
                    continue;
                }

                TenantEntitlement::query()->updateOrCreate(
                    ['tenant_id' => $tenant->id, 'feature' => $entitlement->feature],
                    [
                        'enabled' => $entitlement->enabled,
                        'limit_value' => $entitlement->limit_value,
                        'source' => TenantEntitlement::SOURCE_PACKAGE,
                    ],
                );
            }

            // Anything the old plan granted that this one does not.
            TenantEntitlement::query()
                ->where('source', TenantEntitlement::SOURCE_PACKAGE)
                ->when($features !== [], fn ($query) => $query->whereNotIn('feature', $features))
                ->delete();

            Entitlements::forget($tenant->id);
        });
    }

    protected function endCurrent(Carbon $at): void
    {
        Subscription::query()->active()->get()->each(function (Subscription $subscription) use ($at) {
            $subscription->update([
                'status' => Subscription::STATUS_CANCELLED,
                'cancelled_at' => $subscription->cancelled_at ?? $at,
                'ends_at' => $at,
            ]);
        });
    }

    protected function periodEnd(Package $package, Carbon $from): Carbon
    {
        return $package->billing_period === Package::PERIOD_YEARLY
            ? $from->copy()->addYear()
            : $from->copy()->addMonth();
    }
}
