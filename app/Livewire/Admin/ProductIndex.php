<?php

namespace App\Livewire\Admin;

use App\Models\Product;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('layouts.admin')]
#[Title('Products')]
class ProductIndex extends Component
{
    use WithPagination;

    #[Url]
    public string $search = '';

    #[Url]
    public string $status = '';

    public string $message = '';

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function archive(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->update(['status' => Product::STATUS_ARCHIVED]);

        $this->message = "{$product->name} was put away. It is no longer in the shop.";
    }

    public function putBackOnSale(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $product->update(['status' => Product::STATUS_ACTIVE, 'published_at' => $product->published_at ?? now()]);

        $this->message = "{$product->name} is back on sale.";
    }

    public function delete(int $productId): void
    {
        $product = Product::findOrFail($productId);
        $name = $product->name;
        $product->delete();

        $this->message = "{$name} was deleted.";
    }

    public function render()
    {
        $products = Product::query()
            ->with(['brand', 'variants.inventory'])
            ->when($this->search !== '', fn ($query) => $query->where('name', 'ilike', '%'.$this->search.'%'))
            ->when($this->status !== '', fn ($query) => $query->where('status', $this->status))
            ->orderByDesc('id')
            ->paginate(15);

        return view('livewire.admin.product-index', ['products' => $products]);
    }
}
