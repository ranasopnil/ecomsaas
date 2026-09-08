<?php

namespace App\Livewire\Super;

use App\Models\Concerns\TenantScope;
use App\Models\Subscription;
use App\Models\SubscriptionPayment;
use App\Models\Tenant;
use App\Services\Billing\Payments;
use App\Services\Billing\Renewals;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;

/**
 * Money shops say they have sent, and money they owe.
 *
 * The screen staff open every morning. Confirming a payment here is the only
 * thing that carries a shop's plan forward — the money itself moved by bKash
 * or bank before anybody got here.
 */
#[Layout('layouts.super')]
#[Title('Money due')]
class BillingIndex extends Component
{
    public string $show = 'waiting';

    /** The payment being decided, and the note going with it. */
    public ?int $deciding = null;

    public string $note = '';

    public string $message = '';

    public string $messageType = 'status';

    public function decide(int $paymentId): void
    {
        $this->deciding = $this->deciding === $paymentId ? null : $paymentId;
        $this->note = '';
    }

    public function confirm(int $paymentId, Payments $payments): void
    {
        $payment = $this->find($paymentId);

        if ($payment === null) {
            return;
        }

        $tenant = Tenant::find($payment->tenant_id);

        if ($tenant === null) {
            return;
        }

        $payments->confirm($payment, $tenant, auth('admin')->user(), trim($this->note));

        $this->deciding = null;
        $this->note = '';
        $this->say($tenant->name.' is paid up.');
    }

    public function reject(int $paymentId, Payments $payments): void
    {
        $payment = $this->find($paymentId);

        if ($payment === null) {
            return;
        }

        if (trim($this->note) === '') {
            $this->say('Say why you could not find it. The shop sees this.', 'error');

            return;
        }

        $payments->reject($payment, auth('admin')->user(), trim($this->note));

        $this->deciding = null;
        $this->note = '';
        $this->say('Marked as not found, and the shop has been told why.');
    }

    /**
     * One payment, from whichever shop sent it. Staff work across shops, so
     * this deliberately steps outside the per-shop rule — and only here.
     */
    protected function find(int $paymentId): ?SubscriptionPayment
    {
        return SubscriptionPayment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->whereKey($paymentId)
            ->first();
    }

    protected function say(string $message, string $type = 'status'): void
    {
        $this->message = $message;
        $this->messageType = $type;
    }

    public function render()
    {
        $payments = SubscriptionPayment::query()
            ->withoutGlobalScope(TenantScope::class)
            ->when($this->show === 'waiting', fn ($q) => $q->waiting())
            ->latest('id')
            ->take(60)
            ->get();

        $shops = Tenant::query()->whereIn('id', $payments->pluck('tenant_id')->unique())->get()->keyBy('id');

        // Every shop whose plan has run out or is running out.
        $due = Subscription::query()
            ->withoutGlobalScope(TenantScope::class)
            ->active()
            ->whereIn('status', [Subscription::STATUS_PAST_DUE, Subscription::STATUS_TRIALING])
            ->with('package')
            ->orderBy('current_period_ends_at')
            ->get();

        return view('livewire.super.billing-index', [
            'payments' => $payments,
            'shops' => $shops,
            'due' => $due,
            'dueShops' => Tenant::query()->whereIn('id', $due->pluck('tenant_id')->unique())->get()->keyBy('id'),
            'waitingCount' => SubscriptionPayment::query()
                ->withoutGlobalScope(TenantScope::class)->waiting()->count(),
            'graceDays' => Renewals::GRACE_DAYS,
        ]);
    }
}
