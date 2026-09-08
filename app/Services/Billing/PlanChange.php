<?php

namespace App\Services\Billing;

use App\Exceptions\PlanChangeRefused;
use App\Facades\Tenancy;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\Tenant;
use App\Support\Money;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Moving a shop between plans.
 *
 * Two different problems wearing the same coat. Going up is easy: the shop
 * pays the difference for the days it has left and gets the bigger plan as
 * soon as the money is found. Going down is booked for the renewal date, so
 * the shop keeps what it has already paid for and money never has to move
 * backwards — there are no refunds here to get wrong.
 *
 * A shop that is bigger than the plan it wants to drop to is told what is
 * over and by how much. Nothing of its own is ever deleted to make it fit.
 */
class PlanChange
{
    public function __construct(protected Usage $usage) {}

    /**
     * Is this plan a step up from what the shop pays now?
     */
    public function isUpgrade(Subscription $from, Package $to, Tenant $tenant): bool
    {
        return $this->priceFor($to, $tenant)->minor > $from->price_minor;
    }

    /**
     * What a shop would pay today to move up, counting only the days it has
     * left. Never less than nothing.
     */
    public function differenceToday(Subscription $from, Package $to, Tenant $tenant): Money
    {
        $new = $this->priceFor($to, $tenant);

        $whole = $this->periodDays($from);
        $left = $this->daysLeft($from);

        if ($whole <= 0 || $left <= 0) {
            return $new;
        }

        // Worked in whole units of the smallest coin, so nothing is ever
        // rounded into or out of existence.
        $unusedOnOld = intdiv($from->price_minor * $left, $whole);
        $costOfNew = intdiv($new->minor * $left, $whole);

        return new Money(max(0, $costOfNew - $unusedOnOld), $new->currency, $new->exponent);
    }

    /**
     * What is over, if this shop moved to that plan.
     *
     * @return array<int, array{label: string, used: int, allowance: int}>
     */
    public function overshootOn(Package $package): array
    {
        $allowances = [];

        foreach ($package->entitlements as $entitlement) {
            if (config('features.'.$entitlement->feature.'.type') !== 'limit') {
                continue;
            }

            $allowances[$entitlement->feature] = $entitlement->enabled ? $entitlement->limit_value : 0;
        }

        return $this->usage->overshoot($allowances);
    }

    /**
     * Book a move down for the renewal date.
     *
     * @throws PlanChangeRefused
     */
    public function scheduleDowngrade(Subscription $subscription, Package $to, Tenant $tenant): Subscription
    {
        $this->assertSellableHere($to, $tenant);

        if ($subscription->package_id === $to->id) {
            throw PlanChangeRefused::alreadyOnIt($to->name);
        }

        $over = $this->overshootOn($to);

        if ($over !== []) {
            throw PlanChangeRefused::tooMuchInTheShop($to->name, $over);
        }

        $subscription->update([
            'scheduled_package_id' => $to->id,
            'scheduled_change_at' => $subscription->current_period_ends_at ?? now(),
        ]);

        return $subscription->refresh();
    }

    /**
     * Change one's mind about a booked move.
     */
    public function cancelScheduled(Subscription $subscription): Subscription
    {
        $subscription->update(['scheduled_package_id' => null, 'scheduled_change_at' => null]);

        return $subscription->refresh();
    }

    /**
     * The moment has come: put the shop on the plan it booked.
     *
     * Held rather than forced if the shop has grown past the smaller plan
     * since booking it — a shop is never cut down to fit.
     */
    public function applyScheduled(Subscription $subscription, Tenant $tenant): ?Subscription
    {
        $to = $subscription->scheduledPackage;

        if ($to === null) {
            return null;
        }

        if ($this->overshootOn($to) !== []) {
            // Left booked, so the next run tries again and staff can see it.
            return null;
        }

        $this->cancelScheduled($subscription);

        return app(SubscribeToPackage::class)->handle($tenant, $to);
    }

    public function priceFor(Package $package, Tenant $tenant): Money
    {
        $price = $package->priceIn($tenant->currency);

        if ($price === null) {
            throw PlanChangeRefused::notPricedHere($package->name, $tenant->currency);
        }

        return $price;
    }

    protected function assertSellableHere(Package $package, Tenant $tenant): void
    {
        $this->priceFor($package, $tenant);
    }

    /**
     * How many days the shop's current stretch of time runs for.
     */
    protected function periodDays(Subscription $subscription): int
    {
        $from = $subscription->trial_ends_at ?? $subscription->starts_at;
        $to = $subscription->current_period_ends_at;

        if ($from === null || $to === null) {
            return 0;
        }

        return max(0, (int) round($from->diffInDays($to, false)));
    }

    protected function daysLeft(Subscription $subscription): int
    {
        $to = $subscription->current_period_ends_at;

        return $to === null ? 0 : max(0, (int) ceil(Carbon::now()->diffInDays($to, false)));
    }

    /**
     * The plans a shop may move to, priced in its own money.
     *
     * @return Collection<int, Package>
     */
    public function choices(Tenant $tenant)
    {
        return Package::query()
            ->sellable()
            ->with(['prices', 'entitlements'])
            ->get()
            ->filter(fn (Package $package) => $package->priceIn($tenant->currency) !== null)
            ->values();
    }

    /**
     * The shop's own subscription, or nothing.
     */
    public function current(): ?Subscription
    {
        return Tenancy::check()
            ? Subscription::query()->active()->with(['package.entitlements', 'scheduledPackage'])->latest('id')->first()
            : null;
    }
}
