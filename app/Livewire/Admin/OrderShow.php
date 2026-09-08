<?php

namespace App\Livewire\Admin;

use App\Exceptions\CourierFailed;
use App\Exceptions\OrderStepRefused;
use App\Models\Courier;
use App\Models\Order;
use App\Services\Couriers\CourierFactory;
use App\Services\Orders\OrderFlow;
use App\Support\Money;
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

    /** What the courier's own module asks for — Pathao wants a city, zone and area. */
    public array $booking = [];

    /** What the courier last said about the parcel, when the shopkeeper asked. */
    public string $courierSays = '';

    /** The cash-from-courier form, when it is open. */
    public bool $collecting = false;

    /** What the courier handed over, as the shopkeeper types it. */
    public string $cash = '';

    public string $cash_note = '';

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
        $this->booking = [];
        $this->tracking_code = (string) $this->order->tracking_code;
        $this->courier_id = $this->order->courier_id ?? Courier::inUse()->first()?->id;
    }

    public function cancelStep(): void
    {
        $this->reset(['step', 'reason', 'courier_id', 'tracking_code', 'booking']);
        $this->resetErrorBag();
    }

    /**
     * A different courier asks for different things, so nothing chosen for
     * the last one is carried over to the next.
     */
    public function updatedCourierId(): void
    {
        $this->booking = [];
    }

    public function take(string $status, OrderFlow $flow): void
    {
        try {
            $this->order = $flow->apply($this->order, $status, [
                'reason' => $this->reason,
                'courier_id' => $this->courier_id,
                'tracking_code' => $this->tracking_code,
                ...$this->booking,
            ]);
        } catch (OrderStepRefused|CourierFailed $e) {
            // A courier that would not take the parcel leaves the order
            // exactly where it was, which is what the shopkeeper needs.
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

    /**
     * The courier is handing over what it collected. Opens with the whole
     * amount still owed already filled in, because that is what usually
     * arrives — but the shopkeeper can type what actually came.
     */
    public function collect(): void
    {
        $this->resetErrorBag();

        $this->collecting = true;
        $this->cash = $this->stillOwed()->toDecimal();
        $this->cash_note = '';
    }

    public function cancelCollecting(): void
    {
        $this->reset(['collecting', 'cash', 'cash_note']);
        $this->resetErrorBag();
    }

    public function recordCash(OrderFlow $flow): void
    {
        try {
            $amount = Money::fromDecimal(
                trim($this->cash) === '' ? '0' : trim($this->cash),
                $this->order->currency,
                $this->order->currency_exponent,
            );
        } catch (\InvalidArgumentException) {
            $this->addError('cash', 'Write the amount in figures, like 260 or 260.50.');

            return;
        }

        try {
            $this->order = $flow->cashFromCourier($this->order, $amount, $this->cash_note);
        } catch (OrderStepRefused $e) {
            $this->addError('cash', $e->getMessage());

            return;
        }

        $this->order->load(['lines', 'payment', 'courier', 'events']);
        $this->cancelCollecting();

        $this->dispatch('toast', [
            'text' => $amount->toDisplay().' '.$amount->currency.' entered in your book.',
            'tone' => 'ok',
        ]);
    }

    /**
     * Ask the courier where the parcel is. It reads and changes nothing —
     * whether the order moves is still the shopkeeper's decision.
     */
    public function askCourier(CourierFactory $couriers): void
    {
        $courier = $this->order->courier;

        if ($courier === null || ! $courier->isReady()) {
            return;
        }

        try {
            $driver = $couriers->for($courier);
            $this->courierSays = $driver->statusFrom($driver->status($this->order));
        } catch (CourierFailed $e) {
            $this->courierSays = '';
            $this->dispatch('toast', ['text' => $e->getMessage(), 'tone' => 'bad']);
        }
    }

    /**
     * What the courier still has of this shop's money.
     */
    public function stillOwed(): Money
    {
        return new Money(
            max(0, $this->order->total_minor - $this->order->cod_received_minor),
            $this->order->currency,
            $this->order->currency_exponent,
        );
    }

    /**
     * The choices for one thing the courier asks about, or none while what it
     * depends on has not been chosen yet.
     *
     * @param  array{key: string, label: string, depends_on: ?string}  $ask
     * @return array<string|int, string>
     */
    protected function optionsFor($driver, array $ask): array
    {
        $needs = $ask['depends_on'];

        if ($needs !== null && ($this->booking[$needs] ?? '') === '') {
            return [];
        }

        try {
            return $driver->optionsFor($ask['key'], $this->booking);
        } catch (CourierFailed) {
            // A courier that will not answer must not take the screen down
            // with it. The shopkeeper sees an empty list and a plain message.
            return [];
        }
    }

    public function render()
    {
        $flow = app(OrderFlow::class);

        $chosen = $this->courier_id === null ? null : Courier::find($this->courier_id);
        $driver = $chosen?->isReady() ? app(CourierFactory::class)->for($chosen) : null;

        return view('livewire.admin.order-show', [
            'steps' => $flow->steps($this->order),
            'needs' => $this->step === '' ? null : $flow->needs($this->step),
            'couriers' => Courier::inUse(),
            // What the chosen courier wants to know, and the choices for each.
            'asks' => $driver === null ? [] : array_map(fn (array $ask) => [
                ...$ask,
                'options' => $this->optionsFor($driver, $ask),
            ], $driver->asks()),
            'booksItself' => $driver !== null,
            'canAskCourier' => $this->order->courier?->isReady()
                && $this->order->tracking_code !== null,
            'waitingForCash' => $flow->isWaitingForCash($this->order),
            'stillOwed' => $this->stillOwed(),
        ])->title('Order '.$this->order->reference);
    }
}
