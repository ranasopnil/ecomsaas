<?php

namespace App\Livewire\Admin;

use App\Models\Order;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

/**
 * Every order the shop has taken, newest first, grouped by what has to happen
 * to it next.
 *
 * The tab a shopkeeper opens on is the one with work in it: new orders that
 * nobody has looked at yet.
 */
#[Layout('layouts.admin')]
#[Title('Orders')]
class OrderIndex extends Component
{
    use WithPagination;

    public string $search = '';

    #[Url(as: 'show')]
    public string $show = 'open';

    /**
     * Each tab, and the statuses behind it.
     *
     * @var array<string, array{label: string, statuses: array<int, string>}>
     */
    public const TABS = [
        'open' => ['label' => 'Needs you', 'statuses' => [
            Order::STATUS_PLACED, Order::STATUS_APPROVED, Order::STATUS_PROCESSING,
            Order::STATUS_HANDED_OVER, Order::STATUS_NOT_DELIVERED,
        ]],
        'new' => ['label' => 'New', 'statuses' => [Order::STATUS_PLACED]],
        'approved' => ['label' => 'Approved', 'statuses' => [Order::STATUS_APPROVED]],
        'processing' => ['label' => 'Being packed', 'statuses' => [Order::STATUS_PROCESSING]],
        'handed_over' => ['label' => 'With the courier', 'statuses' => [Order::STATUS_HANDED_OVER]],
        'not_delivered' => ['label' => 'Not delivered', 'statuses' => [Order::STATUS_NOT_DELIVERED]],
        'delivered' => ['label' => 'Delivered', 'statuses' => [Order::STATUS_DELIVERED]],
        'unfinished' => ['label' => 'Unfinished and cancelled', 'statuses' => [
            Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED,
        ]],
        'all' => ['label' => 'Everything', 'statuses' => []],
    ];

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedShow(): void
    {
        $this->resetPage();
    }

    public function showing(string $tab): void
    {
        $this->show = array_key_exists($tab, self::TABS) ? $tab : 'open';
        $this->resetPage();
    }

    public function render()
    {
        $tab = self::TABS[$this->show] ?? self::TABS['open'];

        $orders = Order::query()
            ->when($tab['statuses'] !== [], fn (Builder $q) => $q->whereIn('status', $tab['statuses']))
            ->when($this->search !== '', function (Builder $q) {
                $wanted = '%'.trim($this->search).'%';

                $q->where(fn (Builder $inner) => $inner
                    ->where('reference', 'ilike', $wanted)
                    ->orWhere('customer_name', 'ilike', $wanted)
                    ->orWhere('customer_phone', 'ilike', $wanted)
                    ->orWhere('tracking_code', 'ilike', $wanted));
            })
            ->withCount('lines')
            ->latest('id')
            ->paginate(20);

        // One pass over the shop's own orders, rather than a count per tab.
        $byStatus = Order::query()
            ->select('status', DB::raw('count(*) as total'))
            ->groupBy('status')
            ->pluck('total', 'status');

        return view('livewire.admin.order-index', [
            'orders' => $orders,
            'tabs' => self::TABS,
            'counts' => collect(self::TABS)->map(fn (array $definition) => $definition['statuses'] === []
                ? $byStatus->sum()
                : collect($definition['statuses'])->sum(fn (string $status) => $byStatus->get($status, 0))),
            'takenToday' => Order::whereNotIn('status', [Order::STATUS_PENDING_PAYMENT, Order::STATUS_CANCELLED])
                ->whereDate('placed_at', today())
                ->count(),
        ]);
    }
}
