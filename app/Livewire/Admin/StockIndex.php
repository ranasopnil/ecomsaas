<?php

namespace App\Livewire\Admin;

use App\Models\ProductVariant;
use App\Services\Catalogue\InventoryService;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Stock')]
class StockIndex extends Component
{
    use WithPagination;

    public string $search = '';

    public bool $onlyProblems = false;

    /** @var array<int, string> variant id => counted figure */
    public array $counted = [];

    public string $message = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedOnlyProblems(): void
    {
        $this->resetPage();
    }

    public function saveCount(int $variantId): void
    {
        $variant = ProductVariant::findOrFail($variantId);
        $value = $this->counted[$variantId] ?? null;

        if ($value === null || $value === '' || ! is_numeric($value)) {
            $this->message = 'Enter the number you counted.';

            return;
        }

        app(InventoryService::class)->setTo($variant, (int) $value, 'Counted in the stock screen');

        $this->message = 'Stock updated for '.$variant->product->name.' ('.$variant->choiceLabel().').';
        unset($this->counted[$variantId]);
    }

    public function render()
    {
        $variants = ProductVariant::query()
            ->with(['product', 'inventory', 'optionValues'])
            ->whereHas('product', fn ($query) => $query
                ->when($this->search !== '', fn ($q) => $q->where('name', 'ilike', '%'.$this->search.'%')))
            ->when($this->onlyProblems, fn ($query) => $query
                ->whereHas('inventory', fn ($q) => $q
                    ->where('track_inventory', true)
                    ->where(fn ($inner) => $inner
                        ->where('available', '<=', 0)
                        ->orWhereColumn('available', '<=', 'low_stock_threshold'))))
            ->orderBy('product_id')
            ->orderBy('position')
            ->paginate(20);

        return view('livewire.admin.stock-index', ['variants' => $variants]);
    }
}
