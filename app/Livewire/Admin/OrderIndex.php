<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every order the shop has taken, newest first.
 *
 * Orders waiting on a payment that never arrived are shown too, greyed: a
 * shopkeeper should be able to see that somebody tried.
 */
#[Layout('layouts.admin')]
#[Title('Orders')]
class OrderIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public string $show = 'real';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function render()
    {
        $orders = Order::query()
            ->when($this->show === 'real', fn (Builder $q) => $q->where('status', Order::STATUS_PLACED))
            ->when($this->show === 'unfinished', fn (Builder $q) => $q->whereIn('status', [
                Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED,
            ]))
            ->when($this->search !== '', function (Builder $q) {
                $wanted = '%'.trim($this->search).'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('reference', 'ilike', $wanted)
                    ->orWhere('customer_name', 'ilike', $wanted)
                    ->orWhere('customer_phone', 'ilike', $wanted));
            })
            ->withCount('lines')
            ->latest('id')
            ->paginate(20);

        return view('livewire.admin.order-index', [
            'orders' => $orders,
            'waiting' => Order::where('status', Order::STATUS_PENDING_PAYMENT)->count(),
            'takenToday' => Order::where('status', Order::STATUS_PLACED)
                ->whereDate('placed_at', today())
                ->count(),
        ]);
    }
}
