<?php

namespace App\Livewire\Admin;

use App\Exceptions\PlanChangeRefused;
use App\Facades\Tenancy;
use App\Models\Addon;
use App\Models\Package;
use App\Models\SubscriptionAddon;
use App\Models\SubscriptionPayment;
use App\Services\Billing\Payments;
use App\Services\Billing\PlanChange;
use App\Services\Billing\Usage;
use App\Support\Money;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * What a shop is paying for, how much of it is used, and how to change it.
 *
 * This page stays open even when the rest of the dashboard is closed for
 * non-payment — otherwise a shop could not tell us it had paid.
 */
#[Layout('layouts.admin')]
#[Title('Your plan')]
class PlanIndex extends Component
{
    /** The "I have paid" form. */
    public bool $paying = false;

    public string $amount = '';

    public string $method = 'bkash';

    public string $reference = '';

    public string $payNote = '';

    /** The plan being looked at in the change panel. */
    public ?int $lookingAt = null;

    /** The add-on being bought, and how many. */
    public ?int $buying = null;

    public int $quantity = 1;

    public function mount(): void
    {
        $this->amount = $this->due()?->toDecimal() ?? '';
    }

    /*
     * ------------------------------------------------------- telling us
     */

    public function startPaying(): void
    {
        $this->resetErrorBag();

        $this->paying = true;
        $this->amount = $this->due()?->toDecimal() ?? '';
        $this->reference = '';
        $this->payNote = '';
    }

    public function cancelPaying(): void
    {
        $this->reset(['paying', 'reference', 'payNote']);
        $this->resetErrorBag();
    }

    public function tellUs(Payments $payments): void
    {
        $this->validate([
            'method' => ['required', 'in:'.implode(',', array_keys(SubscriptionPayment::METHODS))],
            'reference' => ['nullable', 'string', 'max:120'],
            'payNote' => ['nullable', 'string', 'max:250'],
        ]);

        $shop = Tenancy::current();

        try {
            $money = Money::fromDecimal(
                trim($this->amount) === '' ? '0' : trim($this->amount),
                $shop->currency,
                (int) $shop->currency_exponent,
            );
        } catch (\InvalidArgumentException) {
            $this->addError('amount', 'Write the amount in figures, like 5990 or 5990.50.');

            return;
        }

        if ($money->minor <= 0) {
            $this->addError('amount', 'Say how much you sent.');

            return;
        }

        $payments->claim(
            $money,
            SubscriptionPayment::PURPOSE_RENEWAL,
            $this->method,
            trim($this->reference) ?: null,
            trim($this->payNote) ?: null,
            [],
            app(PlanChange::class)->current(),
        );

        $this->cancelPaying();

        $this->dispatch('toast', [
            'text' => 'Thank you. We will check for it and your plan will carry on as soon as we find it.',
            'tone' => 'ok',
        ]);
    }

    /*
     * ------------------------------------------------------ changing plan
     */

    public function look(int $packageId): void
    {
        $this->resetErrorBag();
        $this->lookingAt = $this->lookingAt === $packageId ? null : $packageId;
    }

    public function moveUp(int $packageId, PlanChange $plans, Payments $payments): void
    {
        $shop = Tenancy::current();
        $subscription = $plans->current();
        $package = Package::find($packageId);

        if ($subscription === null || $package === null) {
            return;
        }

        try {
            $payment = $payments->requestUpgrade($subscription, $package, $shop);
        } catch (PlanChangeRefused $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        $this->lookingAt = null;

        $this->dispatch('toast', [
            'text' => 'Send '.$payment->amount->toDisplay().' '.$payment->amount->currency
                .' and tell us below. '.$package->name.' starts the moment we find it.',
            'tone' => 'ok',
        ]);
    }

    public function moveDown(int $packageId, PlanChange $plans): void
    {
        $subscription = $plans->current();
        $package = Package::find($packageId);

        if ($subscription === null || $package === null) {
            return;
        }

        try {
            $plans->scheduleDowngrade($subscription, $package, Tenancy::current());
        } catch (PlanChangeRefused $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        $this->lookingAt = null;

        $this->dispatch('toast', [
            'text' => 'Booked. You keep '.$subscription->package->name.' until it runs out, then move to '.$package->name.'.',
            'tone' => 'ok',
        ]);
    }

    public function keepMyPlan(PlanChange $plans): void
    {
        $subscription = $plans->current();

        if ($subscription !== null) {
            $plans->cancelScheduled($subscription);
        }

        $this->dispatch('toast', ['text' => 'You are staying where you are.', 'tone' => 'ok']);
    }

    /*
     * ----------------------------------------------------------- add-ons
     */

    public function startBuying(int $addonId): void
    {
        $this->resetErrorBag();
        $this->buying = $this->buying === $addonId ? null : $addonId;
        $this->quantity = 1;
    }

    public function buy(int $addonId, Payments $payments): void
    {
        $addon = Addon::find($addonId);

        if ($addon === null) {
            return;
        }

        try {
            $payment = $payments->requestAddon($addon, $this->quantity, Tenancy::current());
        } catch (PlanChangeRefused $e) {
            $this->addError('plan', $e->getMessage());

            return;
        }

        $this->buying = null;

        $this->dispatch('toast', [
            'text' => 'Send '.$payment->amount->toDisplay().' and tell us below. It switches on when we find it.',
            'tone' => 'ok',
        ]);
    }

    /*
     * ------------------------------------------------------------ render
     */

    protected function due(): ?Money
    {
        $subscription = app(PlanChange::class)->current();

        return $subscription === null ? null : app(Payments::class)->amountDue($subscription);
    }

    public function render(PlanChange $plans, Usage $usage)
    {
        $shop = Tenancy::current();
        $subscription = $plans->current();

        return view('livewire.admin.plan-index', [
            'shop' => $shop,
            'subscription' => $subscription,
            'due' => $subscription === null ? null : app(Payments::class)->amountDue($subscription),
            'counted' => $usage->counted(),
            'switches' => $usage->switches(),
            'choices' => $plans->choices($shop),
            'plans' => $plans,
            'addons' => Addon::query()->sellable()->with('prices')->get()
                ->filter(fn (Addon $addon) => $addon->priceIn($shop->currency) !== null),
            'mine' => SubscriptionAddon::query()
                ->whereIn('status', [SubscriptionAddon::STATUS_ACTIVE, SubscriptionAddon::STATUS_PENDING])
                ->with('addon')->get(),
            'history' => SubscriptionPayment::query()->latest('id')->take(12)->get(),
            'methods' => SubscriptionPayment::METHODS,
        ]);
    }
}
