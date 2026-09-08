<?php

namespace App\Livewire\Admin;

use App\Exceptions\OrderStepRefused;
use App\Models\Courier;
use App\Models\Order;
use App\Services\Orders\OrderFlow;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One order, and the next thing the shopkeeper can do with it.
 *
 * The money on this screen is never editable: what was agreed at checkout is
 * what it says. What can change is where the parcel has got to, and every one
 * of those changes is written into the order's own history.
 */
#[Layout('layouts.admin')]
class OrderShow extends Component
{
    public Order $order;

    /** The step whose form is open, when a step needs something typed in. */
    public string $step = '';

    public string $reason = '';

    public ?int $courier_id = null;

    public string $tracking_code = '';

    public function mount(Order $order): void
    {
        // Scoped by the tenant rule on the model, so one shop can never open
        // another shop's order.
        $this->order = $order->load(['lines', 'payment', 'courier', 'events']);
    }

    /**
     * A step that needs nothing is taken at once. One that needs a reason or a
     * courier opens its form first — nothing is decided by a single click when
     * somebody has to say why.
     */
    public function choose(string $status, OrderFlow $flow): void
    {
        $this->resetErrorBag();

        if (! $flow->can($this->order, $status)) {
            return;
        }

        if ($flow->needs($status) === null) {
            $this->take($status, $flow);

            return;
        }

        $this->step = $status;
        $this->reason = '';
        $this->tracking_code = (string) $this->order->tracking_code;
        $this->courier_id = $this->order->courier_id ?? Courier::inUse()->first()?->id;
    }

    public function cancelStep(): void
    {
        $this->reset(['step', 'reason', 'courier_id', 'tracking_code']);
        $this->resetErrorBag();
    }

    public function take(string $status, OrderFlow $flow): void
    {
        try {
            $this->order = $flow->apply($this->order, $status, [
                'reason' => $this->reason,
                'courier_id' => $this->courier_id,
                'tracking_code' => $this->tracking_code,
            ]);
        } catch (OrderStepRefused $e) {
            $this->addError('step', $e->getMessage());
            $this->dispatch('toast', ['text' => $e->getMessage(), 'tone' => 'bad']);

            return;
        }

        $this->order->load(['lines', 'payment', 'courier', 'events']);
        $this->cancelStep();

        $this->dispatch('toast', [
            'text' => $this->order->reference.' is now '.mb_strtolower($this->order->statusLabel()).'.',
            'tone' => 'ok',
        ]);
    }

    public function render()
    {
        $flow = app(OrderFlow::class);

        return view('livewire.admin.order-show', [
            'steps' => $flow->steps($this->order),
            'needs' => $this->step === '' ? null : $flow->needs($this->step),
            'couriers' => Courier::inUse(),
        ])->title('Order '.$this->order->reference);
    }
}
