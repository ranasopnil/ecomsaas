<?php

namespace App\Livewire\Admin;

use App\Exceptions\PlanChangeRefused;
use App\Facades\Tenancy;
use App\Models\Addon;
use App\Models\SubscriptionAddon;
use App\Services\Billing\Payments;
use App\Services\Billing\PlanChange;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Extras a shop can buy on top of its plan.
 *
 * A shop that has outgrown fifty products but needs nothing else on the next
 * plan up has no reason to pay for the whole of it. This is how it stays.
 *
 * Nothing bought here works until the money is found: an extra sits waiting
 * beside the others, granting nothing, until staff confirm the payment.
 */
#[Layout('layouts.admin')]
#[Title('Add-ons')]
class AddonIndex extends Component
{
    /** The add-on being bought, and how many of it. */
    public ?int $buying = null;

    public int $quantity = 1;

    public function start(int $addonId): void
    {
        $this->resetErrorBag();

        $this->buying = $this->buying === $addonId ? null : $addonId;
        $this->quantity = 1;
    }

    public function buy(int $addonId, Payments $payments): void
    {
        $addon = Addon::query()->sellable()->whereKey($addonId)->first();

        if ($addon === null) {
            return;
        }

        try {
            $payment = $payments->requestAddon($addon, $this->quantity, Tenancy::current());
        } catch (PlanChangeRefused $e) {
            $this->addError('buying', $e->getMessage());

            return;
        }

        $this->buying = null;

        $this->dispatch('toast', [
            'text' => 'Send '.$payment->amount->toDisplay().' '.$payment->amount->currency
                .' and tell us on your plan page. It switches on the moment we find it.',
            'tone' => 'ok',
        ]);
    }

    public function render(PlanChange $plans)
    {
        $shop = Tenancy::current();

        return view('livewire.admin.addon-index', [
            'shop' => $shop,
            'subscription' => $plans->current(),
            // Only the ones actually sold in this shop's own money.
            'addons' => Addon::query()->sellable()->with('prices')->get()
                ->filter(fn (Addon $addon) => $addon->priceIn($shop->currency) !== null)
                ->values(),
            'mine' => SubscriptionAddon::query()
                ->whereIn('status', [SubscriptionAddon::STATUS_ACTIVE, SubscriptionAddon::STATUS_PENDING])
                ->with('addon')
                ->latest('id')
                ->get(),
        ]);
    }
}
