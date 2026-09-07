<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use Livewire\Attributes\Layout;
use Livewire\Component;

/**
 * One order, exactly as it was agreed. Nothing on this screen can change it.
 */
#[Layout('layouts.admin')]
class OrderShow extends Component
{
    public Order $order;

    public function mount(Order $order): void
    {
        // Scoped by the tenant rule on the model, so one shop can never open
        // another shop's order.
        $this->order = $order->load(['lines', 'payment']);
    }

    public function render()
    {
        return view('livewire.admin.order-show')->title('Order '.$this->order->reference);
    }
}
