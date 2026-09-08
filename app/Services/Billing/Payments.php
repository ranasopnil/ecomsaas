<?php

namespace App\Services\Billing;

use App\Exceptions\PlanChangeRefused;
use App\Facades\Entitlements;
use App\Facades\Tenancy;
use App\Models\Addon;
use App\Models\Admin;
use App\Models\AuditLog;
use App\Models\LedgerEntry;
use App\Models\Package;
use App\Models\Subscription;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\Accounts\Ledger;
use App\Support\Money;
use Illuminate\Support\Facades\DB;

/**
 * A shop paying the platform.
 *
 * The money itself moves by bKash, bank transfer or hand — the platform never
 * takes it. What happens here is the record either side: a shop writing down
 * what it sent, and a member of staff saying whether it arrived. Only the
 * second of those moves a plan on.
 *
 * Nothing is ever edited. A payment that was confirmed by mistake is not
 * un-confirmed; the plan is put right by hand and the reason written down.
 */
class Payments
{
    public function __construct(
        protected PlanChange $plans,
        protected Renewals $renewals,
        protected Ledger $ledger,
    ) {}

    /**
     * What a shop owes for its next stretch of time: the plan, plus anything
     * it has bought on top.
     */
    public function amountDue(Subscription $subscription): Money
    {
        $plan = $subscription->price;

        $extras = SubscriptionAddon::query()->inForce()->get()
            ->reduce(fn (int $carry, SubscriptionAddon $bought) => $carry + $bought->total()->minor, 0);

        return new Money($plan->minor + $extras, $plan->currency, $plan->exponent);
    }

    /**
     * The shop says it has paid. Nothing changes yet.
     *
     * @param  array<string, mixed>  $meta
     */
    public function claim(
        Money $amount,
        string $purpose,
        ?string $method,
        ?string $reference,
        ?string $note = null,
        array $meta = [],
        ?Subscription $subscription = null,
    ): SubscriptionPayment {
        return SubscriptionPayment::create([
            'tenant_id' => Tenancy::id(),
            'subscription_id' => $subscription?->id,
            'amount_minor' => $amount->minor,
            'currency' => $amount->currency,
            'currency_exponent' => $amount->exponent,
            'purpose' => $purpose,
            'method' => $method,
            'reference' => $reference,
            'note' => $note,
            'meta' => $meta === [] ? null : $meta,
            'status' => SubscriptionPayment::STATUS_CLAIMED,
            'claimed_at' => now(),
        ]);
    }

    /**
     * Staff found the money. This is the only thing that moves a plan on.
     */
    public function confirm(SubscriptionPayment $payment, Tenant $tenant, ?Admin $by = null, string $note = ''): SubscriptionPayment
    {
        if (! $payment->isWaiting()) {
            return $payment;
        }

        return Tenancy::run($tenant, function () use ($payment, $tenant, $by, $note) {
            DB::transaction(function () use ($payment, $tenant, $by, $note) {
                $subscription = Subscription::query()->active()->latest('id')->first();

                $payment->update([
                    'status' => SubscriptionPayment::STATUS_CONFIRMED,
                    'confirmed_at' => now(),
                    'confirmed_by' => $by?->id,
                    'decision_note' => $note !== '' ? $note : null,
                    'subscription_id' => $payment->subscription_id ?? $subscription?->id,
                ]);

                match ($payment->purpose) {
                    SubscriptionPayment::PURPOSE_UPGRADE => $this->applyUpgrade($payment, $tenant),
                    SubscriptionPayment::PURPOSE_ADDON => $this->applyAddon($payment),
                    default => $subscription === null ? null : $this->renewals->paidUpTo($subscription, $payment),
                };

                // The shop's own book: money it spent, so its accounts read
                // properly without anybody typing it in twice.
                $this->ledger->record($payment->amount, [
                    'direction' => LedgerEntry::OUT,
                    'kind' => LedgerEntry::KIND_EXPENSE,
                    'description' => 'Shop plan — '.mb_strtolower($payment->purposeLabel()),
                    'source_key' => 'subscription-payment:'.$payment->id,
                ], null);
            });

            // Staff reaching across shops. The project rule is that every
            // one of those is written down, with who did it.
            AuditLog::record(
                'subscription_payment.confirmed',
                $tenant,
                $payment,
                $note,
                ['amount_minor' => $payment->amount_minor, 'currency' => $payment->currency, 'purpose' => $payment->purpose],
            );

            Entitlements::forget($tenant->id);

            return $payment->refresh();
        });
    }

    /**
     * Staff could not find it. The shop is told why.
     */
    public function reject(SubscriptionPayment $payment, ?Admin $by = null, string $why = ''): SubscriptionPayment
    {
        if (! $payment->isWaiting()) {
            return $payment;
        }

        $payment->update([
            'status' => SubscriptionPayment::STATUS_REJECTED,
            'confirmed_at' => now(),
            'confirmed_by' => $by?->id,
            'decision_note' => $why !== '' ? $why : null,
        ]);

        AuditLog::record(
            'subscription_payment.rejected',
            Tenant::find($payment->tenant_id),
            $payment,
            $why,
        );

        return $payment->refresh();
    }

    /**
     * A shop asking for an extra on top of its plan. It grants nothing until
     * the money is found.
     */
    public function requestAddon(Addon $addon, int $quantity, Tenant $tenant, ?string $method = null, ?string $reference = null): SubscriptionPayment
    {
        $price = $addon->priceIn($tenant->currency);

        if ($price === null) {
            throw PlanChangeRefused::notPricedHere($addon->name, $tenant->currency);
        }

        $quantity = max(1, min(20, $quantity));

        $bought = SubscriptionAddon::create([
            'tenant_id' => $tenant->id,
            'addon_id' => $addon->id,
            'quantity' => $quantity,
            'price_minor' => $price->minor,
            'currency' => $price->currency,
            'currency_exponent' => $price->exponent,
            'status' => SubscriptionAddon::STATUS_PENDING,
            'starts_at' => now(),
        ]);

        return $this->claim(
            $price->times($quantity),
            SubscriptionPayment::PURPOSE_ADDON,
            $method,
            $reference,
            $addon->what().($quantity > 1 ? ' ×'.$quantity : ''),
            ['subscription_addon_id' => $bought->id, 'addon_id' => $addon->id, 'quantity' => $quantity],
        );
    }

    /**
     * A shop asking to move up. The bigger plan starts when the money is
     * found, not when the button is pressed.
     */
    public function requestUpgrade(Subscription $subscription, Package $to, Tenant $tenant, ?string $method = null, ?string $reference = null): SubscriptionPayment
    {
        $difference = $this->plans->differenceToday($subscription, $to, $tenant);

        return $this->claim(
            $difference,
            SubscriptionPayment::PURPOSE_UPGRADE,
            $method,
            $reference,
            'Moving to '.$to->name,
            ['package_id' => $to->id],
            $subscription,
        );
    }

    protected function applyUpgrade(SubscriptionPayment $payment, Tenant $tenant): void
    {
        $package = Package::find($payment->meta['package_id'] ?? 0);

        if ($package !== null) {
            app(SubscribeToPackage::class)->handle($tenant, $package);
        }
    }

    protected function applyAddon(SubscriptionPayment $payment): void
    {
        $bought = SubscriptionAddon::find($payment->meta['subscription_addon_id'] ?? 0);

        $bought?->update(['status' => SubscriptionAddon::STATUS_ACTIVE, 'starts_at' => now()]);
    }
}
