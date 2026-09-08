<?php

namespace App\Services\Billing;

use App\Facades\Tenancy;
use App\Models\OutboxEvent;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use Illuminate\Support\Carbon;

/**
 * The clock a subscription runs on.
 *
 * A trial ends, a month runs out, a payment does not arrive. Somebody has to
 * notice, and nobody was: a renewal date used to be written down once and
 * never looked at again.
 *
 * What this will never do is take a shop's storefront down. A shop that has
 * not paid loses its own dashboard after the days of grace; its customers
 * keep shopping, and the orders are waiting when it pays. Their customers had
 * no part in a billing problem.
 */
class Renewals
{
    /** Days after a missed renewal before the shop's dashboard closes. */
    public const GRACE_DAYS = 7;

    /** How long before renewal a shop is reminded. */
    public const REMIND_DAYS = 7;

    public function __construct(protected PlanChange $plans) {}

    /**
     * Walk every shop's subscription forward to where it should be today.
     *
     * @return array{checked: int, past_due: int, locked: int, changed: int, reminded: int}
     */
    public function tick(?Carbon $now = null): array
    {
        $now ??= now();
        $tally = ['checked' => 0, 'past_due' => 0, 'locked' => 0, 'changed' => 0, 'reminded' => 0];

        Tenant::query()->each(function (Tenant $tenant) use ($now, &$tally) {
            Tenancy::run($tenant, function () use ($tenant, $now, &$tally) {
                $subscription = Subscription::query()->active()->latest('id')->first();

                if ($subscription === null) {
                    return;
                }

                $tally['checked']++;

                // A trial that has run out is treated exactly like a month
                // that has run out: nothing was paid, so the clock starts.
                $ranOut = $subscription->status === Subscription::STATUS_TRIALING
                    ? $subscription->trial_ends_at
                    : $subscription->current_period_ends_at;

                if ($subscription->status !== Subscription::STATUS_PAST_DUE && $ranOut !== null && $ranOut->lte($now)) {
                    // A move down booked for today happens before the shop is
                    // asked to pay, so it is asked for the smaller amount.
                    if ($this->plans->applyScheduled($subscription, $tenant) !== null) {
                        $tally['changed']++;

                        $subscription = Subscription::query()->active()->latest('id')->first();
                    }

                    if ($subscription !== null) {
                        $this->fallDue($subscription, $ranOut, $now);
                        $tally['past_due']++;
                    }

                    return;
                }

                if ($subscription->isPastDue() && ! $subscription->isLocked()
                    && $subscription->grace_ends_at !== null && $subscription->grace_ends_at->lte($now)) {
                    $this->lock($subscription);
                    $tally['locked']++;

                    return;
                }

                if ($this->shouldRemind($subscription, $now)) {
                    $this->tell($subscription, 'subscription.renewal_due_soon');
                    $tally['reminded']++;
                }
            });
        });

        return $tally;
    }

    /**
     * The money did not arrive. Everything still works, and the shop is told.
     */
    public function fallDue(Subscription $subscription, Carbon $from, ?Carbon $now = null): Subscription
    {
        $subscription->update([
            'status' => Subscription::STATUS_PAST_DUE,
            'grace_ends_at' => $from->copy()->addDays(self::GRACE_DAYS),
            'locked_at' => null,
        ]);

        $this->tell($subscription, 'subscription.past_due');

        return $subscription->refresh();
    }

    /**
     * The days of grace are up. The dashboard closes; the shop stays open.
     */
    public function lock(Subscription $subscription): Subscription
    {
        $subscription->update(['locked_at' => now()]);

        $this->tell($subscription, 'subscription.locked');

        return $subscription->refresh();
    }

    /**
     * A payment was found. The shop is paid up to a new date, and anything
     * that was closed to it opens again.
     */
    public function paidUpTo(Subscription $subscription, SubscriptionPayment $payment): Subscription
    {
        $from = $this->nextPeriodStart($subscription);
        $to = $this->periodEnd($subscription, $from);

        $subscription->update([
            'status' => Subscription::STATUS_ACTIVE,
            'current_period_ends_at' => $to,
            'grace_ends_at' => null,
            'locked_at' => null,
        ]);

        $payment->update(['covers_from' => $from, 'covers_to' => $to]);

        $this->tell($subscription, 'subscription.renewed');

        return $subscription->refresh();
    }

    /**
     * Where the next stretch of time starts.
     *
     * A shop that pays late is not charged for the days it spent chasing the
     * receipt: the new month runs from today, not from the day it lapsed.
     */
    protected function nextPeriodStart(Subscription $subscription): Carbon
    {
        $ends = $subscription->current_period_ends_at;

        return $ends !== null && $ends->isFuture() ? $ends->copy() : now();
    }

    protected function periodEnd(Subscription $subscription, Carbon $from): Carbon
    {
        return $subscription->billing_period === Package::PERIOD_YEARLY
            ? $from->copy()->addYear()
            : $from->copy()->addMonth();
    }

    protected function shouldRemind(Subscription $subscription, Carbon $now): bool
    {
        $ends = $subscription->status === Subscription::STATUS_TRIALING
            ? $subscription->trial_ends_at
            : $subscription->current_period_ends_at;

        if ($ends === null) {
            return false;
        }

        // Exactly once, on the day, so a daily run does not nag.
        return (int) $now->copy()->startOfDay()->diffInDays($ends->copy()->startOfDay(), false) === self::REMIND_DAYS;
    }

    /**
     * Anything that has to be said because of this is written down with it,
     * rather than left to a queue that could lose it.
     */
    protected function tell(Subscription $subscription, string $type): void
    {
        OutboxEvent::create([
            'tenant_id' => $subscription->tenant_id,
            'type' => $type,
            'payload' => [
                'subscription_id' => $subscription->id,
                'package_id' => $subscription->package_id,
                'ends_at' => $subscription->current_period_ends_at?->toIso8601String(),
            ],
            'available_at' => now(),
        ]);
    }
}
